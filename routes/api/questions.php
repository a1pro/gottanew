<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Questions\QuestionController;

Route::prefix('questions')->group(function () {
   Route::get('/{goal}', [QuestionController::class, 'getByGoal']);
});