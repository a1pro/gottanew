<?php
namespace App\Models\Response;

use Illuminate\Database\Eloquent\Model;
use App\Models\Question\Question;
use App\Models\User;
use App\Models\Goal\Goal;



class UserResponse extends Model
{
    protected $fillable = [
        'user_id',
        'guest_session_id',
        'goal_id',
        'user_goal_id',
        'question_id',
        'answer'
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

     public function goal()
    {
        return $this->belongsTo(Goal::class);
    }

    public function userGoal()
    {
        return $this->belongsTo(
            \App\Models\Goal\UserGoal::class,
            'user_goal_id'
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}