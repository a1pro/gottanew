<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Personality\PersonalityController;


Route::prefix('personality')->group(function () {

   Route::post('/', [PersonalityController::class, 'store']);

});