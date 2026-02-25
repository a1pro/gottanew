<?php
// routes/api/coach.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Coach\OnboardingController;
use App\Http\Controllers\Api\Coach\CoachController;

// Apply CORS middleware directly to public routes
Route::middleware(['cors'])->group(function () {
    Route::get('/coaches', [CoachController::class, 'index']);
    Route::get('/coaches/{id}', [CoachController::class, 'show']);
});


Route::middleware(['role:coach','cors'])->prefix('coach')->group(function () {
    Route::get('/onboarding/status', [OnboardingController::class, 'getStatus']);
    Route::post('/onboarding/profile', [OnboardingController::class, 'saveProfile']);
    Route::post('/onboarding/availability', [OnboardingController::class, 'saveAvailability']);
    Route::post('/onboarding/boundaries', [OnboardingController::class, 'saveBoundaries']);
    Route::get('/availability', [OnboardingController::class, 'getAvailability']);
});

Route::middleware(['auth:sanctum', 'role:coach', 'cors'])->prefix('coach')->group(function () {
    Route::get('/onboarding/status', [OnboardingController::class, 'getStatus']);
    Route::post('/onboarding/profile', [OnboardingController::class, 'saveProfile']);
    Route::post('/onboarding/availability', [OnboardingController::class, 'saveAvailability']);
    Route::post('/onboarding/boundaries', [OnboardingController::class, 'saveBoundaries']);
    Route::get('/availability', [OnboardingController::class, 'getAvailability']);
});