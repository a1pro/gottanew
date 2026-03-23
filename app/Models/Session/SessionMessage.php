<?php

namespace App\Models\Session;

use Illuminate\Database\Eloquent\Model;

class SessionMessage extends Model
{
    protected $fillable = [
        'session_id',
        'sender_id',
        'message',
        'attachments',
    ];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(CoachingSession::class, 'session_id');
    }

    public function sender()
    {
        return $this->belongsTo(\App\Models\User::class, 'sender_id');
    }
}
