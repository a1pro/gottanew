<?php
// routes/api/coach.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Coach\OnboardingController;


Route::middleware(['auth:sanctum', 'role:coach', 'cors'])->prefix('coach')->group(function () {
    Route::get('/onboarding/status', [OnboardingController::class, 'getStatus']);
    Route::post('/onboarding/profile', [OnboardingController::class, 'saveProfile']);
    Route::post('/onboarding/availability', [OnboardingController::class, 'saveAvailability']);
    Route::post('/onboarding/boundaries', [OnboardingController::class, 'saveBoundaries']);
    Route::get('/availability', [OnboardingController::class, 'getAvailability']);
});