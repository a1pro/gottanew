<?php
// routes/api/coach.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Coach\OnboardingController;
use App\Http\Controllers\Api\Coach\ApplicationController;

// Public coach routes (for applicants)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/coach/application/submit', [ApplicationController::class, 'submit']);
    Route::get('/coach/application/status', [ApplicationController::class, 'status']);
});

Route::middleware(['auth:sanctum', 'role:coach', 'cors'])->prefix('coach')->group(function () {
    Route::get('/onboarding/status', [OnboardingController::class, 'getStatus']);
    Route::post('/onboarding/profile', [OnboardingController::class, 'saveProfile']);
    Route::post('/onboarding/availability', [OnboardingController::class, 'saveAvailability']);
    Route::post('/onboarding/boundaries', [OnboardingController::class, 'saveBoundaries']);
    Route::get('/availability', [OnboardingController::class, 'getAvailability']);
});