<?php
// routes/api/profile.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\User\UserController;

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/profile', [UserController::class, 'updateProfile']);
});