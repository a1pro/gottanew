<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Analytics\AnalyticsController;

Route::prefix('analytics')->group(function () {

    Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);

});