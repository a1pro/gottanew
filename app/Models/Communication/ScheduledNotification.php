<?php

namespace App\Models\Communication;

use Illuminate\Database\Eloquent\Model;

class ScheduledNotification extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'user_notification_id',
        'reminder_key',
        'category',
        'priority',
        'title',
        'body',
        'action_url',
        'metadata',
        'send_at',
        'status',
        'sent_at',
        'cancelled_at',
        'failed_at',
        'last_error',
    ];

    protected $casts = [
        'metadata' => 'array',
        'send_at' => 'datetime',
        'sent_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function session()
    {
        return $this->belongsTo(\App\Models\Session\CoachingSession::class, 'session_id');
    }

    public function notification()
    {
        return $this->belongsTo(UserNotification::class, 'user_notification_id');
    }
}
