<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use App\Models\Coach\Coach;
use App\Models\Session\CoachingSession;
use App\Http\Controllers\Api\BaseController;

class AdminController extends BaseController
{
    public function users()
    {
        return $this->success(User::latest()->paginate(20));
    }

    public function coaches()
    {
        return $this->success(Coach::latest()->paginate(20));
    }

    public function sessions()
    {
        return $this->success(CoachingSession::latest()->paginate(20));
    }
}