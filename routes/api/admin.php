<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AdminController;

Route::prefix('admin')
->middleware(['auth:sanctum','role:admin'])
->group(function () {

    Route::get('/users', [AdminController::class, 'users']);
    Route::get('/coaches', [AdminController::class, 'coaches']);
    Route::get('/pending-applications', [AdminController::class, 'pendingApplications']);
    Route::post('/approve-coach/{id}', [AdminController::class, 'approveApplication']);
    Route::get('/sessions', [AdminController::class, 'sessions']);

});