<?php
// routes/api/admin.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Coach\ApplicationController;

Route::prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::put('/users/{id}/toggle-status', [AdminController::class, 'toggleUserStatus']);
    Route::post('/users/{id}/assign-role', [AdminController::class, 'assignRole']);
    Route::delete('/users/{id}/remove-role/{roleId}', [AdminController::class, 'removeRole']);

    // Coach management routes
    Route::get('/coaches', [AdminController::class, 'getAllCoaches']);
    Route::post('/coaches/{id}/approve', [AdminController::class, 'approveCoach']);

     // Coach application management
    Route::get('/applications/pending', [ApplicationController::class, 'getPendingApplications']);
    Route::get('/applications/all', [ApplicationController::class, 'getAllApplications']);
    Route::post('/applications/{id}/approve', [ApplicationController::class, 'approve']);
        Route::post('/applications/{id}/reject', [ApplicationController::class, 'reject']);

        // Coach application routes
    Route::get('/applications/pending', [ApplicationController::class, 'getPendingApplications']);
    Route::get('/applications/all', [ApplicationController::class, 'getAllApplications']);
    Route::post('/applications/{id}/approve', [ApplicationController::class, 'approve']);
    Route::post('/applications/{id}/reject', [ApplicationController::class, 'reject']);
});