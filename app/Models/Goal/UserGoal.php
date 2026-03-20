<?php
namespace App\Models\Goal;

use Illuminate\Database\Eloquent\Model;

class UserGoal extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'progress_percentage',
        'status',
        'target_date',
        'source_session_id',
    ];

    protected $casts = [
        'target_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function session()
    {
        return $this->belongsTo(\App\Models\Session\CoachingSession::class, 'source_session_id');
    }

    public function tasks()
    {
        return $this->hasMany(UserTask::class, 'goal_id');
    }
}