<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\PayoutController;

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'role:admin'])
    ->group(function () {
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/coaches', [AdminController::class, 'coaches']);
        Route::get('/pending-applications', [AdminController::class, 'pendingApplications']);
        Route::post('/coaches/invite', [AdminController::class, 'inviteCoach']);
        Route::post('/approve-coach/{id}', [AdminController::class, 'approveApplication']);

        Route::get('/sessions', [AdminController::class, 'sessions']);
        Route::get('/failed-sessions', [AdminController::class, 'failedSessions']);

        Route::get('/transcripts', [AdminController::class, 'transcripts']);
        Route::get('/transcripts/{id}', [AdminController::class, 'transcript']);

        Route::get('/finance/overview', [PayoutController::class, 'overview']);
        Route::get('/client-wallets', [PayoutController::class, 'clientWallets']);
        Route::get('/token-grants', [PayoutController::class, 'tokenGrants']);
        Route::post('/token-grants', [PayoutController::class, 'grantTokens']);
        Route::get('/payout-cycles', [PayoutController::class, 'cycles']);
        Route::post('/payout-cycles/generate', [PayoutController::class, 'generate']);
        Route::post('/payout-cycles/{id}/approve', [PayoutController::class, 'approve']);
        Route::post('/payout-cycles/{id}/mark-paid', [PayoutController::class, 'markPaid']);
    });