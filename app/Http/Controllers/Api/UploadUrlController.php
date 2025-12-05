<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UploadUrlController extends Controller
{
    public function __construct(
        private SupabaseStorageService $storageService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id' => ['required', 'uuid', 'exists:tasks,id'],
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', Rule::in(['image/jpeg', 'image/png', 'application/pdf'])],
        ]);

        $taskId = $validated['task_id'];
        $filename = $validated['filename'];
        $mimeType = $validated['mime_type'];

        $task = Task::find($taskId);

        if (! $task) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $userId = $request->attributes->get('supabase_user_id');

        if ($task->creator_id !== $userId && $task->assignee_id !== $userId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $randomPrefix = Str::random(32);
        $path = "tasks/{$taskId}/{$randomPrefix}_{$filename}";

        $result = $this->storageService->generateSignedUploadUrl($path, 60);

        if (! $result) {
            return response()->json(['message' => 'Failed to generate upload URL'], 500);
        }

        return response()->json($result);
    }
}
