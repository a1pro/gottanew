<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Client\GoalController;
use App\Http\Controllers\Api\Client\TaskController;
use App\Http\Controllers\Api\Client\SessionController;
use App\Http\Controllers\Api\Client\ConnectionRequestController;

Route::prefix('client')->middleware('auth:sanctum')->group(function () {
    Route::get('/goals', [GoalController::class, 'index']);
    Route::post('/goals', [GoalController::class, 'store']);
    Route::delete('/goals/{goal}', [GoalController::class, 'destroy']);

    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);

    Route::get('/sessions', [SessionController::class, 'index']);
    Route::post('/sessions/book', [SessionController::class, 'store']);
    Route::post('/sessions/instant', [SessionController::class, 'instant']);
    Route::get('/sessions/{id}', [SessionController::class, 'show']);

    Route::get('/session-requests', [ConnectionRequestController::class, 'index']);
    Route::post('/session-requests', [ConnectionRequestController::class, 'store']);
});