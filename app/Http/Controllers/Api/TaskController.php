<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    public function __construct(
        private SupabaseStorageService $storageService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $userId = $request->attributes->get('supabase_user_id');

        $query = Task::query()->forUser($userId);

        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->has('due_date_from')) {
            $query->where('due_date', '>=', $request->input('due_date_from'));
        }

        if ($request->has('due_date_to')) {
            $query->where('due_date', '<=', $request->input('due_date_to'));
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $tasks = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $tasks->getCollection()->transform(function ($task) {
            return $this->formatTask($task);
        });

        return response()->json($tasks);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $userId = $request->attributes->get('supabase_user_id');

        $task = Task::create([
            'creator_id' => $userId,
            'assignee_id' => $request->input('assignee_id'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'due_date' => $request->input('due_date'),
            'priority' => $request->input('priority'),
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $extension = $file->getClientOriginalExtension();
            $filename = Str::random(32).'_'.$file->getClientOriginalName();
            $path = "tasks/{$task->id}/{$filename}";

            if ($this->storageService->upload($file, $path)) {
                $task->update([
                    'attachment_key' => $path,
                    'attachment_mime' => $file->getMimeType(),
                ]);

                $task->refresh();
            }
        }

        return response()->json($this->formatTask($task), 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $userId = $request->attributes->get('supabase_user_id');

        $task = Task::find($id);

        if (! $task) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        if ($task->creator_id !== $userId && $task->assignee_id !== $userId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($this->formatTask($task));
    }

    public function update(UpdateTaskRequest $request, string $id): JsonResponse
    {
        $userId = $request->attributes->get('supabase_user_id');

        $task = Task::find($id);

        if (! $task) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        if ($task->creator_id !== $userId && $task->assignee_id !== $userId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($request->has('is_completed') && $task->assignee_id !== $userId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $task->update($request->only([
            'title',
            'description',
            'due_date',
            'priority',
            'is_completed',
        ]));

        return response()->json($this->formatTask($task));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $userId = $request->attributes->get('supabase_user_id');

        $task = Task::find($id);

        if (! $task) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        if ($task->creator_id !== $userId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($task->attachment_key) {
            $this->storageService->delete($task->attachment_key);
        }

        $task->delete();

        return response()->json(null, 204);
    }

    private function formatTask(Task $task): array
    {
        $data = [
            'id' => $task->id,
            'creator_id' => $task->creator_id,
            'assignee_id' => $task->assignee_id,
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $task->due_date?->toIso8601String(),
            'priority' => $task->priority,
            'is_completed' => $task->is_completed,
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ];

        if ($task->attachment_key) {
            $signedUrl = $this->storageService->generateSignedUrl($task->attachment_key, 60);
            $data['attachment_url'] = $signedUrl;
            $data['attachment_mime'] = $task->attachment_mime;
        }

        return $data;
    }
}
