<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Session\SessionPortalController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sessions/{id}', [SessionPortalController::class, 'show']);
    Route::get('/sessions/{id}/join', [SessionPortalController::class, 'join']);
    Route::post('/sessions/{id}/state', [SessionPortalController::class, 'updateState']);
    Route::put('/sessions/{id}/notes', [SessionPortalController::class, 'saveNotes']);
    Route::post('/sessions/{id}/end', [SessionPortalController::class, 'end']);
});