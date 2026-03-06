<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Client\GoalController;
use App\Http\Controllers\Api\Client\SessionController;
use App\Http\Controllers\Api\Client\ConnectionRequestController;

Route::prefix('client')->group(function () {

    Route::get('/goals', [GoalController::class, 'index']);
    Route::post('/goals', [GoalController::class, 'store']);

    Route::get('/sessions', [SessionController::class, 'index']);
    Route::post('/sessions', [SessionController::class, 'store']);

    Route::post('/connection-requests', [ConnectionRequestController::class, 'store']);
});