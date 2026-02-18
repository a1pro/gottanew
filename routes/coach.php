<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CoachController;

Route::prefix('coach')->group(function () {
    Route::get('/dashboard', [CoachController::class, 'dashboard']);
});
