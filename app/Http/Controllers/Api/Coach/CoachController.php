<?php

namespace App\Http\Controllers\Api\Coach;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\BaseController;
use App\Models\Coach\Coach;

class CoachController extends BaseController
{

    public function index()
        {
            return Coach::where('is_active', true)->get();
        }
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

    public function show($id)
        {
            $coach = Coach::findOrFail($id);
            return $this->success($coach);
        }
}