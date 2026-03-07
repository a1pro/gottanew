<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Goals\GoalController;

Route::prefix('goals')->group(function () {

    Route::get('/', [GoalController::class, 'index']);
    Route::get('/{id}', [GoalController::class, 'show']);

    Route::post('/', [GoalController::class, 'store']);
    Route::put('/{id}', [GoalController::class, 'update']);
    Route::delete('/{id}', [GoalController::class, 'destroy']);

});