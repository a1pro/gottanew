<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\PayoutController;
use App\Http\Controllers\Api\Admin\AdminNotificationController;
use App\Http\Controllers\Api\Admin\SessionRequestAdminController;

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin'])
    ->group(function () {
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/coaches', [AdminController::class, 'coaches']);
        Route::get('/pending-applications', [AdminController::class, 'pendingApplications']);
        Route::post('/coaches/invite', [AdminController::class, 'inviteCoach']);
        Route::put('/coaches/{id}/status', [AdminController::class, 'updateStatus']);
        Route::post('/approve-coach/{id}', [AdminController::class, 'approveApplication']);

        Route::get('/session-requests', [SessionRequestAdminController::class, 'index']);
        Route::get('/session-requests/assignable-coaches', [SessionRequestAdminController::class, 'assignableCoaches']);
        Route::post('/session-requests/{id}/approve', [SessionRequestAdminController::class, 'approve']);
        Route::post('/session-requests/{id}/reject', [SessionRequestAdminController::class, 'reject']);

        Route::get('/sessions', [AdminController::class, 'sessions']);
        Route::get('/failed-sessions', [AdminController::class, 'failedSessions']);

        Route::get('/transcripts', [AdminController::class, 'transcripts']);
        Route::get('/transcripts/{id}', [AdminController::class, 'transcript']);
        Route::get('/transcripts/{id}/verify-daily', [AdminController::class, 'verifyDaily']);
        Route::post('/transcripts/{id}/sync', [AdminController::class, 'syncTranscript']);
        Route::post('/transcripts/{id}/generate-summary', [AdminController::class, 'generateSummary']);

        Route::get('/delivery-logs', [AdminNotificationController::class, 'deliveryLogs']);

        Route::get('/finance/overview', [PayoutController::class, 'overview']);
        Route::get('/client-wallets', [PayoutController::class, 'clientWallets']);
        Route::get('/token-grants', [PayoutController::class, 'tokenGrants']);
        Route::post('/token-grants', [PayoutController::class, 'grantTokens']);
        Route::get('/payout-cycles', [PayoutController::class, 'cycles']);
        Route::post('/payout-cycles/generate', [PayoutController::class, 'generate']);
        Route::post('/payout-cycles/{id}/approve', [PayoutController::class, 'approve']);
        Route::post('/payout-cycles/{id}/mark-paid', [PayoutController::class, 'markPaid']);
    });