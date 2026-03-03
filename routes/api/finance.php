<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Finance\WalletController;
use App\Http\Controllers\Api\Finance\TransactionController;

Route::prefix('finance')->group(function () {

    Route::get('/wallet', [WalletController::class, 'show']);
    Route::post('/wallet/deposit', [WalletController::class, 'deposit']);

    Route::get('/transactions', [TransactionController::class, 'index']);
});