<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_can_delete_task()
    {
        $creatorId = '00000000-0000-0000-0000-000000000001';
        $task = Task::factory()->create([
            'creator_id' => $creatorId,
        ]);

        // Mock the middleware to simulate authenticated user
        $this->withMiddleware(\App\Http\Middleware\SupabaseAuth::class);
        $this->instance(\App\Http\Middleware\SupabaseAuth::class, new class($creatorId) {
            private $userId;
            public function __construct($id) { $this->userId = $id; }
            public function handle($request, $next) {
                $request->attributes->set('supabase_user_id', $this->userId);
                return $next($request);
            }
        });

        // Re-bind alias to the mocked middleware instance if necessary, 
        // but simplest is to manually inject attributes if we can bypass middleware,
        // or just use `withoutMiddleware` and manually set request attribute?
        // Laravel's test client processing makes mocking specific middleware tricky inline without swapping the binding.
        // Let's swap the middleware binding globally for this test.
    }

    // Simpler approach: Create a helper trait or method to simulate Supabase login
    protected function actingAsSupabaseUser($userId)
    {
        $this->withoutMiddleware([\App\Http\Middleware\SupabaseAuth::class]);
        
        // We need to inject the attribute into the request that the controller reads.
        // This is tricky with `withoutMiddleware` because the request is recreated.
        // Better: Mock the middleware behavior.
        
        $this->app->bind(\App\Http\Middleware\SupabaseAuth::class, function () use ($userId) {
            return new class($userId) {
                private $id;
                public function __construct($id) { $this->id = $id; }
                public function handle($request, $next) {
                    $request->attributes->set('supabase_user_id', $this->id);
                    return $next($request);
                }
            };
        });
    }

    public function test_creator_can_delete_own_task()
    {
        $creatorId = 'user-1-uuid';
        $this->actingAsSupabaseUser($creatorId);

        $task = Task::factory()->create(['creator_id' => $creatorId]);

        $response = $this->deleteJson("/api/tasks/{$task->id}");
        $response->assertStatus(204);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_non_creator_cannot_delete_task()
    {
        $creatorId = 'user-1-uuid';
        $otherUserId = 'user-2-uuid';
        $this->actingAsSupabaseUser($otherUserId);

        $task = Task::factory()->create(['creator_id' => $creatorId]);

        $response = $this->deleteJson("/api/tasks/{$task->id}");
        $response->assertStatus(403);
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_only_assignee_can_mark_completed()
    {
        $creatorId = 'user-1-uuid';
        $assigneeId = 'user-2-uuid';
        $otherId = 'user-3-uuid';

        $task = Task::factory()->create([
            'creator_id' => $creatorId,
            'assignee_id' => $assigneeId,
            'is_completed' => false,
        ]);

        // Try as random user
        $this->actingAsSupabaseUser($otherId);
        $response = $this->putJson("/api/tasks/{$task->id}", ['is_completed' => true]);
        // Controller checks: if not creator AND not assignee => 403.
        // The controller logic is:
        // if ($task->creator_id !== $userId && $task->assignee_id !== $userId) return 403;
        // if ($request->has('is_completed') && $task->assignee_id !== $userId) return 403;
        $response->assertStatus(403);

        // Try as Creator (but not assignee) -> Should fail for 'is_completed'
        $this->actingAsSupabaseUser($creatorId);
        $response = $this->putJson("/api/tasks/{$task->id}", ['is_completed' => true]);
        $response->assertStatus(403);

        // Try as Assignee -> Should success
        $this->actingAsSupabaseUser($assigneeId);
        $response = $this->putJson("/api/tasks/{$task->id}", ['is_completed' => true]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'is_completed' => true]);
    }
}
