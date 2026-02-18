<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;

Route::prefix('user')->group(function () {
    Route::get('/profile', [UserController::class, 'profile']);
    Route::post('/update', [UserController::class, 'update']);
});