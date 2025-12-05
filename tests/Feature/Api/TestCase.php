<?php

namespace Tests\Feature\Api;

use Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function withAuthenticatedUser(string $userId): static
    {
        return $this->withoutMiddleware(\App\Http\Middleware\SupabaseAuth::class)
            ->withHeader('X-Test-User-Id', $userId);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->resolving(\Illuminate\Http\Request::class, function ($request) {
            if ($request->hasHeader('X-Test-User-Id')) {
                $request->attributes->set('supabase_user_id', $request->header('X-Test-User-Id'));
            }
        });
    }
}
