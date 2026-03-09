<?php
namespace App\Http\Controllers\Api\Ai;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Coach\CoachMatchingService;    
use App\Http\Controllers\Api\BaseController;
use App\Models\Coach\Coach;

class CoachMatchingController extends BaseController
{

    public function match(Request $request)
    {
        $goal_id = $request->goal_id;

        $responses = $request->responses;

        $coaches = Coach::whereHas('specialties', function ($query) use ($goal_id) {
            $query->where('goal_id', $goal_id);
        })
        ->with('user')
        ->get();

        $coaches = $coaches->map(function ($coach) {

            $score = 0;

            $score += 50;

            if ($coach->experience) {
                $score += $coach->experience * 2;
            }

            if ($coach->rating) {
                $score += $coach->rating * 5;
            }

            return [
                'coach' => $coach,
                'score' => $score
            ];
        });

        $sorted = $coaches->sortByDesc('score')->values();

        return response()->json([
            'coaches' => $sorted
        ]);
    }

}