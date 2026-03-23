<?php

namespace App\Models\Session;

use Illuminate\Database\Eloquent\Model;

class CoachingSession extends Model
{
    protected $table = 'coaching_sessions';

    protected $fillable = [
        'client_id',
        'coach_id',
        'scheduled_time',
        'status',
        'duration_minutes',
        'price_amount',
        'price_currency',
        'client_notes',
        'coach_notes',
        'actual_started_at',
        'actual_ended_at',
        'last_activity_at',
        'last_interrupted_at',
        'recovery_deadline_at',
        'recovery_attempts',
        'failure_reason',
        'recovery_context',
    ];

    protected $casts = [
        'scheduled_time' => 'datetime',
        'actual_started_at' => 'datetime',
        'actual_ended_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'last_interrupted_at' => 'datetime',
        'recovery_deadline_at' => 'datetime',
        'recovery_context' => 'array',
    ];

    public function coach()
    {
        return $this->belongsTo(\App\Models\Coach\Coach::class);
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\User::class, 'client_id');
    }

    public function videoDetail()
    {
        return $this->hasOne(\App\Models\Session\SessionVideoDetail::class, 'session_id');
    }

    public function stateLogs()
    {
        return $this->hasMany(SessionStateLog::class, 'session_id');
    }

    public function transactions()
    {
        return $this->hasMany(\App\Models\Finance\Transaction::class, 'session_id');
    }

    public function recording()
    {
        return $this->hasOne(SessionRecording::class, 'session_id');
    }

    public function messages()
    {
        return $this->hasMany(SessionMessage::class, 'session_id');
    }

    public function resources()
    {
        return $this->hasMany(SessionResource::class, 'session_id');
    }

    public function participants()
    {
        return $this->hasMany(SessionParticipant::class, 'session_id');
    }
}
