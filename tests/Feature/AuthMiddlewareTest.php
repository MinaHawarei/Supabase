<?php

namespace Tests\Feature;

use App\Http\Middleware\SupabaseAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuthMiddlewareTest extends TestCase
{
    public function test_missing_token_returns_401()
    {
        $response = $this->getJson('/api/me');
        $response->assertStatus(401);
    }

    public function test_invalid_token_returns_401()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->getJson('/api/me');

        $response->assertStatus(401);
    }

    // Note: Valid token test requires mocking Supabase Auth or having a valid session,
    // which is complex without a real Supabase instance.
    // For now we test that the middleware is at least intercepting.
}
