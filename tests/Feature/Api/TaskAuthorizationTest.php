<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\TaskController;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function createRequestWithUser(string $userId): Request
    {
        $request = Request::create('/api/tasks', 'GET');
        $request->attributes->set('supabase_user_id', $userId);

        return $request;
    }

    public function test_creator_can_delete_task(): void
    {
        $creatorId = Str::uuid()->toString();
        $task = Task::factory()->create([
            'creator_id' => $creatorId,
            'assignee_id' => Str::uuid()->toString(),
        ]);

        $controller = new TaskController(app(\App\Services\SupabaseStorageService::class));
        $request = $this->createRequestWithUser($creatorId);

        $response = $controller->destroy($request, $task->id);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_non_creator_cannot_delete_task_returns_403(): void
    {
        $creatorId = Str::uuid()->toString();
        $otherUserId = Str::uuid()->toString();
        $task = Task::factory()->create([
            'creator_id' => $creatorId,
            'assignee_id' => Str::uuid()->toString(),
        ]);

        $controller = new TaskController(app(\App\Services\SupabaseStorageService::class));
        $request = $this->createRequestWithUser($otherUserId);

        $response = $controller->destroy($request, $task->id);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_only_assignee_can_mark_task_completed(): void
    {
        $creatorId = Str::uuid()->toString();
        $assigneeId = Str::uuid()->toString();
        $task = Task::factory()->create([
            'creator_id' => $creatorId,
            'assignee_id' => $assigneeId,
            'is_completed' => false,
        ]);

        $controller = new TaskController(app(\App\Services\SupabaseStorageService::class));
        $request = Request::create("/api/tasks/{$task->id}", 'PUT', ['is_completed' => true]);
        $request->attributes->set('supabase_user_id', $assigneeId);

        $response = $controller->update(
            app(\App\Http\Requests\UpdateTaskRequest::class)->merge(['is_completed' => true]),
            $task->id
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'is_completed' => true,
        ]);
    }

    public function test_creator_cannot_mark_task_completed_returns_403(): void
    {
        $creatorId = Str::uuid()->toString();
        $assigneeId = Str::uuid()->toString();
        $task = Task::factory()->create([
            'creator_id' => $creatorId,
            'assignee_id' => $assigneeId,
            'is_completed' => false,
        ]);

        $controller = new TaskController(app(\App\Services\SupabaseStorageService::class));
        $request = Request::create("/api/tasks/{$task->id}", 'PUT', ['is_completed' => true]);
        $request->attributes->set('supabase_user_id', $creatorId);

        $updateRequest = app(\App\Http\Requests\UpdateTaskRequest::class)->merge(['is_completed' => true]);
        $updateRequest->attributes->set('supabase_user_id', $creatorId);

        $response = $controller->update($updateRequest, $task->id);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'is_completed' => false,
        ]);
    }
}
