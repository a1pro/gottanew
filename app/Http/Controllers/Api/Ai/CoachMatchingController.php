<?php
namespace App\Http\Controllers\Api\Ai;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Coach\CoachMatchingService;    
use App\Http\Controllers\Api\BaseController;

class CoachMatchingController extends BaseController
{

    public function match(Request $request)
    {

        $service = new CoachMatchingService();

        $coaches = $service->match(

            $request->goal_id,

            $request->responses

        );

        return $this->success([
            'analysis' => 'Recommended coaches based on your responses',
            'recommendations' => $coaches,
            'totalRecommendations' => count($coaches)
        ]);

    }

}