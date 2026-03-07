<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Questions\QuestionController;
use App\Http\Controllers\Api\Responses\ResponseController;  

Route::prefix('response')->group(function () {

   Route::post('/responses',[ResponseController::class,'store']);

});