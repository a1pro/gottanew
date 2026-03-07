<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Coach\CoachController;
use App\Http\Controllers\Api\Coach\AvailabilityController;
use App\Http\Controllers\Api\Coach\PackageController;
use App\Http\Controllers\Api\Coach\CoachMatchController;

Route::prefix('coach')->group(function () {

    Route::get('/profile', [CoachController::class, 'profile']);
    Route::put('/profile', [CoachController::class, 'update']);

    Route::apiResource('availability', AvailabilityController::class);
    Route::apiResource('packages', PackageController::class);

    Route::post('/coach/match', [CoachMatchController::class, 'match']);

});