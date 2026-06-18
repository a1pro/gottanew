<?php

namespace App\Models\Communication;

use Illuminate\Database\Eloquent\Model;
use App\Models\Communication\EmailOutbox;
use App\Models\User;    
use App\Models\Session\CoachingSession;  
use App\Models\Finance\CoachPayout;  
use App\Models\Communication\MessageOutbox;  

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
        return $this->belongsTo(User::class, 'user_id');
    }

    public function session()
    {
        return $this->belongsTo(CoachingSession::class, 'session_id');
    }

    public function coachPayout()
    {
        return $this->belongsTo(CoachPayout::class, 'coach_payout_id');
    }

    public function emailOutboxes()
    {
         return $this->hasOne(EmailOutbox::class, 'user_notification_id');
    }

    public function messageOutboxes()
    {
        return $this->hasOne(MessageOutbox::class, 'user_notification_id');
    }
}

`   