<?php

namespace App\Models\Question;

use Illuminate\Database\Eloquent\Model;
use App\Models\Question\Question;

class QuestionOption extends Model
{
    protected $fillable = [
        'question_id',
        'option_text',
        'score',
        'order'
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}