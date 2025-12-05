<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Services\SupabaseStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class StorageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsSupabaseUser($userId)
    {
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

    public function test_get_signed_upload_url()
    {
        $userId = 'user-1';
        $this->actingAsSupabaseUser($userId);
        
        $task = Task::factory()->create(['creator_id' => $userId]);

        // Mock Storage Service
        $this->mock(SupabaseStorageService::class, function (MockInterface $mock) {
            $mock->shouldReceive('generateSignedUploadUrl')
                ->once()
                ->andReturn([
                    'upload_url' => 'https://supabase.co/upload/signed',
                    'object_path' => 'tasks/1/test.jpg',
                    'expires_in' => 60
                ]);
        });

        $response = $this->postJson('/api/tasks/upload-url', [
            'task_id' => $task->id,
            'filename' => 'test.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'upload_url' => 'https://supabase.co/upload/signed',
                'object_path' => 'tasks/1/test.jpg'
            ]);
    }

    public function test_create_task_with_attachment_key()
    {
        $userId = 'user-1';
        $this->actingAsSupabaseUser($userId);

        $response = $this->postJson('/api/tasks', [
            'title' => 'Task with attachment',
            'priority' => 'high',
            'attachment_key' => 'tasks/temp/image.png',
            'attachment_mime' => 'image/png',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tasks', [
            'title' => 'Task with attachment',
            'attachment_key' => 'tasks/temp/image.png',
            'attachment_mime' => 'image/png',
        ]);
    }
}
