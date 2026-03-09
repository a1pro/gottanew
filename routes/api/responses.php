<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Questions\QuestionController;
use App\Http\Controllers\Api\Responses\ResponseController;  

Route::prefix('responses')->group(function () {

   Route::post('/',[ResponseController::class,'store']);

});