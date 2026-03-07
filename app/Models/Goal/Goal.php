<?php

namespace App\Models\Goal;

use Illuminate\Database\Eloquent\Model;
use App\Models\Question\Question;

class Goal extends Model
{
    protected $table = 'goals';

    protected $fillable = [
        'goal_id',
        'title',
        'description',
        'icon',
        'color',
        'is_active'
    ];


    public function questions()
        {
            return $this->hasMany(Question::class);
        }
}