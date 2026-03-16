<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Coach\CoachController;
use App\Http\Controllers\Api\Coach\AvailabilityController;
use App\Http\Controllers\Api\Coach\PackageController;
use App\Http\Controllers\Api\Coach\CoachMatchController;
use App\Http\Controllers\Api\Coach\SessionController;

Route::prefix('coach')->group(function () {
    Route::get('/coaches', [CoachController::class, 'index']);
    Route::get('/coaches/{id}', [CoachController::class, 'show']);
    Route::apiResource('availability', AvailabilityController::class);
    Route::get('/coaches/{id}/packages', [PackageController::class, 'coachPackages']);
    Route::apiResource('packages', PackageController::class);
    Route::post('/match', [CoachMatchController::class, 'match']);
});

Route::prefix('coach')->middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [CoachController::class, 'profile']);
    Route::put('/profile', [CoachController::class, 'update']);
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::get('/sessions/{id}', [SessionController::class, 'show']);
    Route::put('/sessions/{id}/notes', [SessionController::class, 'saveNotes']);
    Route::post('/sessions/{id}/start', [SessionController::class, 'start']);
});