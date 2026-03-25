<?php

namespace App\Models\Communication;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageOutbox extends Model
{
    use HasFactory;

    protected $table = 'message_outbox';

    protected $fillable = [
        'dedup_key',
        'user_id',
        'user_notification_id',
        'session_id',
        'provider',
        'channel',
        'recipient_phone',
        'sender_id',
        'body',
        'payload',
        'provider_message_id',
        'provider_status',
        'status',
        'attempts',
        'max_attempts',
        'last_error',
        'last_attempt_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'scheduled_for',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'last_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function notification()
    {
        return $this->belongsTo(UserNotification::class, 'user_notification_id');
    }

    public function session()
    {
        return $this->belongsTo(\App\Models\Session\CoachingSession::class, 'session_id');
    }
}
