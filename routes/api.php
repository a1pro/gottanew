<?php

use Illuminate\Support\Facades\Route;

// API v1 routes
Route::prefix('v1')
    ->middleware('cors')
    ->group(function () {

    // Include all route files
    require __DIR__ . '/api/auth.php';
    require __DIR__ . '/api/coach.php';
    require __DIR__ . '/api/client.php';
    require __DIR__ . '/api/admin.php';
    require __DIR__ . '/api/analytics.php';
    require __DIR__ . '/api/finance.php';
    require __DIR__ . '/api/goals.php';
    require __DIR__.'/api/questions.php';
    require __DIR__.'/api/responses.php';
    require __DIR__.'/api/ai.php';  
});