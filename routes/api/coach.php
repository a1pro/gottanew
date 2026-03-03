<?php
// routes/api/coach.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Coach\OnboardingController;
use App\Http\Controllers\Api\Coach\CoachController;
use App\Http\Controllers\Api\Goal\GoalController;


// Apply CORS middleware directly to public routes
Route::middleware(['cors'])->group(function () {

      // Goals
    Route::get('/goals', [GoalController::class, 'index']);
    Route::get('/goals/{id}', [GoalController::class, 'show']);
  

        // Goals management
    Route::post('/goals', [GoalController::class, 'store']);
    Route::put('/goals/{id}', [GoalController::class, 'update']);
    Route::delete('/goals/{id}', [GoalController::class, 'destroy']);
 
    Route::get('/coaches', [CoachController::class, 'index']);
    Route::get('/coaches/{id}', [CoachController::class, 'show']);
});


Route::middleware(['auth:sanctum', 'role:coach', 'cors'])->prefix('coach')->group(function () {
    Route::post('/coach/match', [CoachController::class, 'matchCoaches'])->middleware('auth:sanctum');
    Route::get('/onboarding/status', [OnboardingController::class, 'getStatus']);
    Route::post('/onboarding/profile', [OnboardingController::class, 'saveProfile']);
    Route::post('/onboarding/availability', [OnboardingController::class, 'saveAvailability']);
    Route::post('/onboarding/boundaries', [OnboardingController::class, 'saveBoundaries']);
    Route::get('/availability', [OnboardingController::class, 'getAvailability']);
});