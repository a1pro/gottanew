<?php

namespace App\Http\Controllers\Api\Questions;

use App\Http\Controllers\Controller;
use App\Models\Question\Question;
use Illuminate\Http\Request;


class QuestionController extends Controller
{
    public function getByGoal($goal_id)
    {

        $questions = Question::with('options')
            ->where('goal_id', $goal_id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $questions
        ]);

    }

    
}