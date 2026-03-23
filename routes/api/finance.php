<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Finance\WalletController;
use App\Http\Controllers\Api\Finance\TransactionController;
use App\Http\Controllers\Api\Finance\CoinPackageController;
use App\Http\Controllers\Api\Finance\PaymentController;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('finance')->group(function () {
        Route::get('/wallet', [WalletController::class, 'show']);
        Route::post('/wallet/deposit', [WalletController::class, 'deposit']);
        Route::get('/transactions', [TransactionController::class, 'index']);
    });

    Route::get('/coin-packages', [CoinPackageController::class, 'index']);
    Route::post('/coins/purchase', [PaymentController::class, 'purchase']);
    Route::post('/payments/verify', [PaymentController::class, 'verify']);
});
