<?php

namespace App\Models\Goal;

use Illuminate\Database\Eloquent\Model;

class UserTask extends Model
{
    protected $fillable = [
        'user_id',
        'goal_id',
        'session_id',
        'title',
        'description',
        'is_completed',
        'due_date',
        'completed_at',
        'priority',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function goal()
    {
        return $this->belongsTo(UserGoal::class, 'goal_id');
    }

    public function session()
    {
        return $this->belongsTo(\App\Models\Session\CoachingSession::class, 'session_id');
    }
}