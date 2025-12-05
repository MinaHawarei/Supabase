<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_upload_url_endpoint_returns_valid_structure(): void
    {
        Http::fake([
            config('services.supabase.url').'/storage/v1/object/sign/*' => Http::response([
                'signedURL' => '/storage/v1/object/sign/attachments/tasks/123/test.pdf',
            ]),
        ]);

        $userId = Str::uuid()->toString();
        $task = Task::factory()->create([
            'creator_id' => $userId,
            'assignee_id' => Str::uuid()->toString(),
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\SupabaseAuth::class);

        $response = $this->postJson('/api/tasks/upload-url', [
            'task_id' => $task->id,
            'filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
        ], [
            'X-Test-User-Id' => $userId,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'upload_url',
            'object_path',
            'expires_in',
        ]);
    }

    public function test_creating_task_with_attachment_key_works(): void
    {
        $userId = Str::uuid()->toString();
        $assigneeId = Str::uuid()->toString();
        $attachmentKey = 'tasks/test-id/test-file.pdf';

        $this->withoutMiddleware(\App\Http\Middleware\SupabaseAuth::class);

        $response = $this->postJson('/api/tasks', [
            'title' => 'Test Task',
            'assignee_id' => $assigneeId,
            'description' => 'Test Description',
            'due_date' => now()->addDay()->toDateString(),
            'priority' => 'high',
            'attachment_key' => $attachmentKey,
            'attachment_mime' => 'application/pdf',
        ], [
            'X-Test-User-Id' => $userId,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tasks', [
            'title' => 'Test Task',
            'creator_id' => $userId,
            'assignee_id' => $assigneeId,
            'attachment_key' => $attachmentKey,
            'attachment_mime' => 'application/pdf',
        ]);
    }
}
