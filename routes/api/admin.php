<?php
// routes/api/admin.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AdminController;

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::put('/users/{id}/toggle-status', [AdminController::class, 'toggleUserStatus']);
    Route::post('/users/{id}/assign-role', [AdminController::class, 'assignRole']);
    Route::delete('/users/{id}/remove-role/{roleId}', [AdminController::class, 'removeRole']);
});