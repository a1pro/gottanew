<?php
// routes/api.php

use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [App\Http\Controllers\Api\Auth\AuthController::class, 'register']);
Route::post('/login', [App\Http\Controllers\Api\Auth\AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth
    Route::get('/me', [App\Http\Controllers\Api\Auth\AuthController::class, 'me']);
    Route::post('/logout', [App\Http\Controllers\Api\Auth\AuthController::class, 'logout']);
    
    // User profile (any authenticated user)
    Route::put('/profile', [App\Http\Controllers\Api\User\UserController::class, 'updateProfile']);
    
    // Sessions (shared)
    Route::get('/sessions', [App\Http\Controllers\Api\Shared\SessionController::class, 'index']);
    Route::post('/sessions', [App\Http\Controllers\Api\Shared\SessionController::class, 'store']);
    Route::get('/sessions/{id}', [App\Http\Controllers\Api\Shared\SessionController::class, 'show']);
    Route::put('/sessions/{id}', [App\Http\Controllers\Api\Shared\SessionController::class, 'update']);
    Route::delete('/sessions/{id}/cancel', [App\Http\Controllers\Api\Shared\SessionController::class, 'cancel']);
    
    // Coaches (public for clients)
    Route::get('/coaches', [App\Http\Controllers\Api\Coach\CoachController::class, 'index']);
    Route::get('/coaches/{id}', [App\Http\Controllers\Api\Coach\CoachController::class, 'show']);
    
    // Coach only routes
    Route::middleware('role:coach')->prefix('coach')->group(function () {
        Route::put('/profile', [App\Http\Controllers\Api\Coach\CoachController::class, 'updateProfile']);
    });
    
    // Admin only routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Api\Admin\AdminController::class, 'dashboard']);
        Route::get('/users', [App\Http\Controllers\Api\Admin\AdminController::class, 'users']);
        Route::put('/users/{id}/toggle-status', [App\Http\Controllers\Api\Admin\AdminController::class, 'toggleUserStatus']);
        Route::post('/users/{id}/assign-role', [App\Http\Controllers\Api\Admin\AdminController::class, 'assignRole']);
        Route::delete('/users/{id}/remove-role/{roleId}', [App\Http\Controllers\Api\Admin\AdminController::class, 'removeRole']);
    });
});