<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseStorageService
{
    private string $baseUrl;

    private string $serviceRoleKey;

    private string $bucket;

    public function __construct()
    {
        $this->baseUrl = config('services.supabase.url');
        $this->serviceRoleKey = config('services.supabase.service_role_key');
        $this->bucket = config('services.supabase.bucket');

        if (! $this->baseUrl || ! $this->serviceRoleKey || ! $this->bucket) {
            throw new \RuntimeException('Supabase storage configuration is incomplete');
        }
    }

    public function upload(UploadedFile $file, string $path): bool
    {
        $url = "{$this->baseUrl}/storage/v1/object/{$this->bucket}/{$path}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceRoleKey}",
            'Content-Type' => $file->getMimeType(),
            'x-upsert' => 'true',
        ])->put($url, $file->getContent());

        if (! $response->successful()) {
            Log::error('Supabase storage upload failed', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }

    public function generateSignedUrl(string $path, int $expiresIn = 60): ?string
    {
        $url = "{$this->baseUrl}/storage/v1/object/sign/{$this->bucket}/{$path}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceRoleKey}",
            'Content-Type' => 'application/json',
        ])->post($url, [
            'expires_in' => $expiresIn,
        ]);

        if (! $response->successful()) {
            Log::error('Supabase signed URL generation failed', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();
        $signedUrl = $data['signedURL'] ?? null;

        if ($signedUrl) {
            return "{$this->baseUrl}{$signedUrl}";
        }

        return null;
    }

    public function delete(string $path): bool
    {
        $url = "{$this->baseUrl}/storage/v1/object/{$this->bucket}/{$path}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceRoleKey}",
        ])->delete($url);

        if (! $response->successful() && $response->status() !== 404) {
            Log::error('Supabase storage delete failed', [
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }
}

