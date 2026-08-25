<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Coach\CoachController;
use App\Http\Controllers\Api\Coach\AvailabilityController;
use App\Http\Controllers\Api\Coach\PackageController;
use App\Http\Controllers\Api\Coach\CoachMatchController;
use App\Http\Controllers\Api\Coach\SessionController;
use App\Http\Controllers\Api\Coach\EarningsController;

Route::prefix('coach')->group(function () {
    Route::get('/coaches', [CoachController::class, 'index']);
    Route::get('/coaches/{id}', [CoachController::class, 'show']);
    Route::get('/coaches/{id}/availability', [AvailabilityController::class, 'publicIndex']);
    Route::get('/coaches/{id}/available-slots', [AvailabilityController::class, 'slots']);
    Route::get('/invitation/{token}', [CoachController::class, 'invitation']);
    Route::post('/onboarding/complete', [CoachController::class, 'completeOnboarding']);
    Route::get('/coaches/{id}/packages', [PackageController::class, 'coachPackages']);
    Route::apiResource('packages', PackageController::class);
    Route::post('/match', [CoachMatchController::class, 'match']);
});

Route::prefix('coach')->middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [CoachController::class, 'profile']);
    Route::put('/profile', [CoachController::class, 'update']);
    Route::post('/information-requests/{id}/respond', [CoachController::class, 'respondToInformationRequest']);

    // Route::post('/coach/profile',[CoachController::class, 'updateProfile']);

    Route::get('/coaches/{id}/session-pricing', [AvailabilityController::class, 'pricing']);

    Route::get('/availability', [AvailabilityController::class, 'index']);
    Route::post('/availability', [AvailabilityController::class, 'store']);
    Route::put('/availability/{id}', [AvailabilityController::class, 'update']);
    Route::delete('/availability/{id}', [AvailabilityController::class, 'destroy']);

    Route::get('/earnings', [EarningsController::class, 'index']);
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::get('/sessions/{id}', [SessionController::class, 'show']);
    Route::put('/sessions/{id}/notes', [SessionController::class, 'saveNotes']);
    Route::post('/sessions/{id}/start', [SessionController::class, 'start']);
});
