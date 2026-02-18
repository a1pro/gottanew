<?php
// routes/api.php

use Illuminate\Support\Facades\Route;

// API v1 routes
Route::prefix('v1')->group(function () {
    
    // Include all route files
    require __DIR__ . '/api/auth.php';
    require __DIR__ . '/api/profile.php';
    require __DIR__ . '/api/sessions.php';
    require __DIR__ . '/api/coaches.php';
    require __DIR__ . '/api/coach.php';
    require __DIR__ . '/api/client.php';
    require __DIR__ . '/api/admin.php';
});