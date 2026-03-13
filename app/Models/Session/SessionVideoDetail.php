<?php

namespace App\Models\Session;

use Illuminate\Database\Eloquent\Model;

class SessionVideoDetail extends Model
{
    protected $fillable = [
        'session_id',
        'video_room_id',
        'video_join_url',
        'recording_url',
        'daily_room_name',
        'room_created_at',
    ];

    protected $casts = [
        'room_created_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(CoachingSession::class, 'session_id');
    }
}