<?php

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['supabase.auth'])->group(function () {
    Route::get('/me', MeController::class);

    Route::apiResource('tasks', TaskController::class);
});

