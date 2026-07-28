<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Goals\GoalController as CanonicalGoalController;
use App\Http\Controllers\Api\Client\GoalController as ClientGoalController;

/*
|--------------------------------------------------------------------------
| Canonical goal taxonomy — /goals
| Read is open to any authenticated user (populates the goal picker).
| Write is admin-only.
|--------------------------------------------------------------------------
*/
Route::prefix('goals')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [CanonicalGoalController::class, 'index']);
    Route::get('/{id}', [CanonicalGoalController::class, 'show']);

    Route::middleware('can:admin')->group(function () {
        Route::post('/', [CanonicalGoalController::class, 'store']);
        Route::put('/{id}', [CanonicalGoalController::class, 'update']);
        Route::delete('/{id}', [CanonicalGoalController::class, 'destroy']);
    });
});

/*
|--------------------------------------------------------------------------
| Client's own goals — /client/goals
| A client can only ever read/write their own rows (enforced in controller).
|--------------------------------------------------------------------------
*/
Route::prefix('client/goals')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ClientGoalController::class, 'index']);
    Route::post('/', [ClientGoalController::class, 'store']);
    Route::delete('/{goal}', [ClientGoalController::class, 'destroy']);
});