<?php
// routes/api/sessions.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Shared\SessionController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::post('/sessions', [SessionController::class, 'store']);
    Route::get('/sessions/{id}', [SessionController::class, 'show']);
    Route::put('/sessions/{id}', [SessionController::class, 'update']);
    Route::delete('/sessions/{id}/cancel', [SessionController::class, 'cancel']);
});