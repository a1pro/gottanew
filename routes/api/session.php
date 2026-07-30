<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Session\SessionPortalController;
use App\Http\Controllers\Api\Session\SessionCollaborationController;

Route::get('/sessions/{id}/stream', [SessionCollaborationController::class, 'stream']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sessions/{id}', [SessionPortalController::class, 'show']);
    Route::get('/sessions/{id}/join', [SessionPortalController::class, 'join']);
    Route::get('/sessions/{id}/video', [SessionPortalController::class, 'join']);
    Route::get('/sessions/{id}/validate', [SessionPortalController::class, 'validateSession']);
    Route::post('/sessions/{id}/reconnect', [SessionPortalController::class, 'reconnect']);
    Route::post('/sessions/{id}/interrupt', [SessionPortalController::class, 'markInterrupted']);
    Route::post('/sessions/{id}/recover', [SessionPortalController::class, 'recover']);
    Route::post('/sessions/{id}/state', [SessionPortalController::class, 'updateState']);
    Route::put('/sessions/{id}/notes', [SessionPortalController::class, 'saveNotes']);
    Route::post('/sessions/{id}/end', [SessionPortalController::class, 'end']);
    Route::post('/sessions/{id}/feedback', [SessionPortalController::class, 'saveFeedback']);
    Route::post('/sessions/{id}/coach-response', [SessionPortalController::class, 'coachResponse']);

    Route::get('/sessions/{id}/messages', [SessionCollaborationController::class, 'messages']);
    Route::post('/sessions/{id}/messages', [SessionCollaborationController::class, 'storeMessage']);

    Route::get('/sessions/{id}/resources', [SessionCollaborationController::class, 'resources']);
    Route::post('/sessions/{id}/resources', [SessionCollaborationController::class, 'storeResource']);
    Route::delete('/sessions/{id}/resources/{resourceId}', [SessionCollaborationController::class, 'destroyResource']);

    // Managed Resource Library
    Route::get('/resource-library', [SessionCollaborationController::class, 'managedResources']);
    Route::post('/resource-library', [SessionCollaborationController::class, 'storeManagedResource']);
    Route::delete('/resource-library/{resourceId}', [SessionCollaborationController::class, 'destroyManagedResource']);

    // Share managed resource with current session
    Route::post(
        '/sessions/{id}/resources/from-library/{resourceId}',
        [SessionCollaborationController::class, 'shareManagedResource']
    );

    Route::post('/sessions/{id}/consent', [SessionPortalController::class, 'saveConsent']);
    Route::put('/sessions/{id}/recording', [SessionPortalController::class, 'updateRecording']);
    Route::post('/sessions/{id}/sync-daily-assets', [SessionPortalController::class, 'syncDailyAssets']);
});
