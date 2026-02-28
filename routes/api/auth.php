<?php
// routes/api/auth.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;

// Public auth routes
Route::middleware(['cors'])->group(function () {
    // Public auth routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'cors'])->group(function () {
    Route::get('/profile ', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});