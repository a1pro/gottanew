<?php

namespace App\Http\Controllers\Api\Coach;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\BaseController;

class CoachController extends BaseController
{
    public function profile(Request $request)
    {
        return $this->success(
            $request->user()->coachProfile
        );
    }

    public function update(Request $request)
    {
        $coach = $request->user()->coachProfile;
        $coach->update($request->all());

        return $this->success($coach, 'Profile updated');
    }
}