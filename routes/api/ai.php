<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Ai\CoachMatchingController;


Route::prefix('ai')->group(function () {

  Route::post('/coach-matching',[CoachMatchingController::class,'match']);

});