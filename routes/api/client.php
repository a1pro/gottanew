<?php
// routes/api/client.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Client\QuestionnaireController;
use App\Http\Controllers\Api\Client\MatchingController;

use App\Http\Controllers\Api\SessionController;


Route::middleware(['auth:sanctum', 'role:client'])->prefix('client')->group(function () {
   

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::post('/goals', [DashboardController::class, 'storeGoal']);
    Route::delete('/goals/{id}', [DashboardController::class, 'deleteGoal']);

    Route::post('/tasks', [DashboardController::class, 'storeTask']);
    Route::put('/tasks/{id}', [DashboardController::class, 'updateTask']);
    Route::delete('/tasks/{id}', [DashboardController::class, 'deleteTask']);


    // Questionnaire
    Route::get('/questionnaire/status', [QuestionnaireController::class, 'getStatus']);
    Route::post('/questionnaire/goals', [QuestionnaireController::class, 'saveGoals']);
    Route::post('/questionnaire/personality', [QuestionnaireController::class, 'savePersonality']);
    
    // Matching
    Route::get('/matching/shortlist', [MatchingController::class, 'getShortlist']);
    Route::post('/matching/select', [MatchingController::class, 'selectCoach']);
    Route::get('/coaches/{coachId}/availability', [MatchingController::class, 'getCoachAvailability']);

    Route::post('/sessions/book', [SessionController::class, 'book']);
    Route::get('/sessions/upcoming', [SessionController::class, 'upcoming']);
});