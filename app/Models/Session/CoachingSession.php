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
    ];

    protected $casts = [
        'scheduled_time' => 'datetime',
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
}