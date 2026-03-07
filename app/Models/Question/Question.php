<?php

namespace App\Models\Question;

use Illuminate\Database\Eloquent\Model;
use App\Models\Goal\Goal;
use App\Models\Question\QuestionOption;


class Question extends Model
{
    protected $fillable = [
        'goal_id',
        'question',
        'type',
        'placeholder',
        'order'
    ];

    public function options()
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function goal()
    {
        return $this->belongsTo(Goal::class);
    }
}