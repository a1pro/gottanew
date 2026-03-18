<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Ai\CoachMatchingController;
use App\Http\Controllers\Api\Ai\SessionInsightController;

Route::prefix('ai')->group(function () {
    Route::post('/coach-matching', [CoachMatchingController::class, 'match']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/sessions/{id}/summaries', [SessionInsightController::class, 'show']);
        Route::post('/sessions/{id}/generate-pre-summary', [SessionInsightController::class, 'generatePre']);
        Route::post('/sessions/{id}/generate-post-summary', [SessionInsightController::class, 'generatePost']);
    });
});