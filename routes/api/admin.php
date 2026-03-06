<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AdminController;

Route::prefix('admin')->middleware('role:admin')->group(function () {

    Route::get('/users', [AdminController::class, 'users']);
    Route::get('/coaches', [AdminController::class, 'coaches']);
    Route::get('/sessions', [AdminController::class, 'sessions']);

});