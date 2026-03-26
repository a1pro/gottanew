<?php

namespace App\Models\Session;

use Illuminate\Database\Eloquent\Model;

class SessionRequest extends Model
{
    protected $table = 'session_requests';

    protected $fillable = [
        'client_id',
        'preferred_coach_id',
        'assigned_coach_id',
        'approved_session_id',
        'status',
        'goal_summary',
        'request_notes',
        'admin_notes',
        'viewer_timezone',
        'scheduled_time',
        'reviewed_by',
        'reviewed_at',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'scheduled_time' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(\App\Models\User::class, 'client_id');
    }

    public function preferredCoach()
    {
        return $this->belongsTo(\App\Models\Coach\Coach::class, 'preferred_coach_id');
    }

    public function assignedCoach()
    {
        return $this->belongsTo(\App\Models\Coach\Coach::class, 'assigned_coach_id');
    }

    public function approvedSession()
    {
        return $this->belongsTo(CoachingSession::class, 'approved_session_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }
}
