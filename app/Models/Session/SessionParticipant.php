<?php
namespace App\Models\Session;

use Illuminate\Database\Eloquent\Model;

class SessionParticipant extends Model
{
    protected $fillable = [
        'session_id', 'user_id', 'role',
        'display_name', 'daily_user_id',
        'joined_at', 'left_at', 'meeting_token_issued_at'
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'meeting_token_issued_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(CoachingSession::class, 'session_id');
    }
}
