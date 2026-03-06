<?php

namespace App\Http\Controllers\Api\Client;

use Illuminate\Http\Request;
use App\Models\Goal\UserGoal;
use App\Http\Controllers\Api\BaseController;

class GoalController extends BaseController
{
    public function index(Request $request)
    {
        return $this->success(
            $request->user()->goals
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'category' => 'required'
        ]);

        $goal = $request->user()->goals()->create($request->all());

        return $this->success($goal, 'Goal created');
    }
}