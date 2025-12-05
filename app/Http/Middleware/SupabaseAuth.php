<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use Symfony\Component\HttpFoundation\Response;

class SupabaseAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $jwks = $this->getJwks();

            $parser = new Parser(new \Lcobucci\JWT\Encoding\JoseEncoder);
            $parsedToken = $parser->parse($token);

            if (! $parsedToken instanceof UnencryptedToken) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $kid = $parsedToken->headers()->get('kid');
            $publicKeyPem = $this->findKey($jwks, $kid);

            if (! $publicKeyPem) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $jwtConfig = Configuration::forAsymmetricSigner(
                new \Lcobucci\JWT\Signer\Rsa\Sha256,
                InMemory::empty(),
                InMemory::plainText($publicKeyPem)
            );

            $jwtConfig->setValidationConstraints(
                new SignedWith(new \Lcobucci\JWT\Signer\Rsa\Sha256, InMemory::plainText($publicKeyPem)),
                new StrictValidAt(new \Lcobucci\Clock\SystemClock(new \DateTimeZone('UTC')))
            );

            $constraints = $jwtConfig->validationConstraints();

            if (! $jwtConfig->validator()->validate($parsedToken, ...$constraints)) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $claims = $parsedToken->claims()->all();
            $userId = $claims['sub'] ?? null;

            if (! $userId) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $request->attributes->set('supabase_user_id', $userId);
            $request->attributes->set('supabase_user_email', $claims['email'] ?? null);

            $this->setJwtClaimInDatabase($userId);

        } catch (RequiredConstraintsViolated $e) {
            Log::warning('JWT validation failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Unauthorized'], 401);
        } catch (\Exception $e) {
            Log::error('JWT processing error', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }

    private function getJwks(): array
    {
        $jwksUrl = config('services.supabase.jwks_url');

        if (! $jwksUrl) {
            throw new \RuntimeException('SUPABASE_JWKS_URL is not configured');
        }

        return Cache::remember('supabase_jwks', 3600, function () use ($jwksUrl) {
            $response = Http::get($jwksUrl);

            if (! $response->successful()) {
                throw new \RuntimeException('Failed to fetch JWKS');
            }

            return $response->json();
        });
    }

    private function findKey(array $jwks, ?string $kid): ?string
    {
        if (! isset($jwks['keys'])) {
            return null;
        }

        foreach ($jwks['keys'] as $key) {
            if (($key['kid'] ?? null) === $kid) {
                return $this->convertJwkToPem($key);
            }
        }

        return null;
    }

    private function convertJwkToPem(array $jwk): string
    {
        $modulus = $jwk['n'] ?? null;
        $exponent = $jwk['e'] ?? null;

        if (! $modulus || ! $exponent) {
            throw new \RuntimeException('Invalid JWK format');
        }

        $modulus = $this->base64UrlDecode($modulus);
        $exponent = $this->base64UrlDecode($exponent);

        $components = [
            'modulus' => pack('Ca*a*', 2, $this->encodeLength(strlen($modulus)), $modulus),
            'exponent' => pack('Ca*a*', 2, $this->encodeLength(strlen($exponent)), $exponent),
        ];

        $rsaPublicKey = pack(
            'Ca*a*a*',
            48,
            $this->encodeLength(strlen($components['modulus']) + strlen($components['exponent'])),
            $components['modulus'],
            $components['exponent']
        );

        $rsaOID = pack('H*', '300d06092a864886f70d0101010500');
        $rsaPublicKey = chr(0).$rsaPublicKey;
        $rsaPublicKey = chr(0).$rsaPublicKey;

        $rsaPublicKey = pack(
            'Ca*a*',
            48,
            $this->encodeLength(strlen($rsaOID.$rsaPublicKey)),
            $rsaOID.$rsaPublicKey
        );

        $rsaPublicKey = "-----BEGIN PUBLIC KEY-----\r\n".chunk_split(base64_encode($rsaPublicKey), 64).'-----END PUBLIC KEY-----';

        return $rsaPublicKey;
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }

    private function encodeLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $temp = ltrim(pack('N', $length), chr(0));

        return pack('Ca*', 0x80 | strlen($temp), $temp);
    }

    private function setJwtClaimInDatabase(string $userId): void
    {
        if (config('database.default') !== 'pgsql') {
            return;
        }

        try {
            \Illuminate\Support\Facades\DB::statement(
                "SELECT set_config('request.jwt.claim.sub', :userId, true)",
                ['userId' => $userId]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to set JWT claim in database', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);
        }
    }
}
