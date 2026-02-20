<?php
// app/Models/ClientQuestionnaire.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientQuestionnaire extends Model
{
    protected $table = 'client_questionnaire_responses';
    
    protected $fillable = [
        'client_id', 'goals', 'personality_traits', 'preferences', 'completed'
    ];

    protected $casts = [
        'goals' => 'array',
        'personality_traits' => 'array',
        'preferences' => 'array',
        'completed' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}