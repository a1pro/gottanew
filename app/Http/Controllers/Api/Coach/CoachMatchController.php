<?php

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Coach\CoachMatchingService;   
use App\Models\Goal\Goal;
use App\Models\Question\Question;

class CoachMatchController extends Controller
{

    public function match(Request $request)
    {

        $request->validate([
            'goal_id' => 'required',
            'responses' => 'required|array'
        ]);

        $service = new CoachMatchingService();

        $coaches = $service->match(
            $request->goal_id,
            $request->responses
        );

        return response()->json([
            'success' => true,
            'data' => $coaches
        ]);

    }

}