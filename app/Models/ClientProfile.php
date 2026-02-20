<?php
// app/Models/ClientProfile.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientProfile extends Model
{
    protected $fillable = [
        'user_id', 'goals', 'personality_traits', 'preferences',
        'terms_accepted', 'terms_accepted_at', 'questionnaire_completed'
    ];

    protected $casts = [
        'goals' => 'array',
        'personality_traits' => 'array',
        'preferences' => 'array',
        'terms_accepted' => 'boolean',
        'terms_accepted_at' => 'datetime',
        'questionnaire_completed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}