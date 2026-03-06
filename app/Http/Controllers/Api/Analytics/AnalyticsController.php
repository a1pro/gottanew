<?php

namespace App\Http\Controllers\Api\Analytics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Coach\Coach;
use App\Models\Session\CoachingSession;

class AnalyticsController extends Controller
{
    public function dashboard()
    {
        return response()->json([
            'total_users' => User::count(),
            'total_coaches' => Coach::count(),
            'total_sessions' => CoachingSession::count(),
        ]);
    }
}