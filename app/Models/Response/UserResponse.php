<?php
namespace App\Models\Response;

use Illuminate\Database\Eloquent\Model;
use App\Models\Question\Question;
use App\Models\User;

class UserResponse extends Model
{
    protected $fillable = [
        'user_id',
        'goal_id',
        'question_id',
        'answer'
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}