<?php

namespace App\Models\Session;

use Illuminate\Database\Eloquent\Model;

class GuestSession extends Model
{
    protected $fillable = [
        'session_id',
        'user_id',
        'goal_id',
        'responses',
        'ai_analysis',
        'recommended_coaches',
        'expires_at'
    ];
}