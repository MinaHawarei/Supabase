<?php

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['supabase.auth', 'supabase.rls'])->group(function () {
    Route::get('/me', MeController::class);

    Route::post('/tasks/upload-url', [\App\Http\Controllers\Api\UploadUrlController::class, '__invoke']);

    Route::apiResource('tasks', TaskController::class);
});
