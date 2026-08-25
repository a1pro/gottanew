<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;

Route::prefix('auth')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/coach-apply', [AuthController::class, 'coachApply']);
    Route::get('/coach-application/{email}', [AuthController::class, 'coachApplication']);
    Route::post('/coach-application/{id}/respond', [AuthController::class, 'respondToCoachApplication']);
    Route::post('/set-password', [AuthController::class, 'setPassword']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/profile/photo', [AuthController::class, 'uploadProfilePhoto']);
        Route::delete('/profile/photo', [AuthController::class, 'removeProfilePhoto']);
        Route::delete('/profile/transcripts', [AuthController::class, 'deleteTranscripts']);
        Route::get('/coach-information-requests', [AuthController::class, 'coachInformationRequests']);
        Route::post('/coach-information-requests/{id}/respond', [AuthController::class, 'respondToCoachInformationRequest']);
    });

});