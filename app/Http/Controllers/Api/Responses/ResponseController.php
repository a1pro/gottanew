<?php
namespace App\Http\Controllers\Api\Responses;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Response\UserResponse;

class ResponseController extends Controller
{
    public function store(Request $request)
    {
        foreach ($request->answers as $answer) {

            UserResponse::create([
                'user_id' => auth()->id(),
                'goal_id' => $request->goal_id,
                'question_id' => $answer['question_id'],
                'answer' => $answer['answer']
            ]);

        }

        return response()->json([
            'success' => true
        ]);
    }
}