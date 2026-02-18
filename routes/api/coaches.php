<?php
// routes/api/coaches.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Coach\CoachController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/coaches', [CoachController::class, 'index']);
    Route::get('/coaches/{id}', [CoachController::class, 'show']);
});