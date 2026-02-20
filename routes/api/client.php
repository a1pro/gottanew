<?php
// routes/api/client.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Client\QuestionnaireController;
use App\Http\Controllers\Api\Client\MatchingController;

Route::middleware(['auth:sanctum', 'role:client'])->prefix('client')->group(function () {
    // Questionnaire
    Route::get('/questionnaire/status', [QuestionnaireController::class, 'getStatus']);
    Route::post('/questionnaire/goals', [QuestionnaireController::class, 'saveGoals']);
    Route::post('/questionnaire/personality', [QuestionnaireController::class, 'savePersonality']);
    
    // Matching
    Route::get('/matching/shortlist', [MatchingController::class, 'getShortlist']);
    Route::post('/matching/select', [MatchingController::class, 'selectCoach']);
    Route::get('/coaches/{coachId}/availability', [MatchingController::class, 'getCoachAvailability']);
});