<?php

namespace App\Models\Communication;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'coach_payout_id',
        'category',
        'priority',
        'title',
        'body',
        'action_url',
        'channel',
        'delivery_status',
        'metadata',
        'is_read',
        'read_at',
        'sent_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function session()
    {
        return $this->belongsTo(\App\Models\Session\CoachingSession::class, 'session_id');
    }

    public function coachPayout()
    {
        return $this->belongsTo(\App\Models\Finance\CoachPayout::class, 'coach_payout_id');
    }
}
