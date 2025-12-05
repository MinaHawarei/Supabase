<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_token_returns_401(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Unauthorized',
        ]);
    }

    public function test_invalid_token_returns_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/me');

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Unauthorized',
        ]);
    }

    public function test_empty_authorization_header_returns_401(): void
    {
        $response = $this->withHeader('Authorization', '')
            ->getJson('/api/me');

        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Unauthorized',
        ]);
    }
}
