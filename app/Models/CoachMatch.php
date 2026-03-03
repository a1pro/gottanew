<?php
// app/Models/CoachMatch.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachMatch extends Model
{
    protected $fillable = [
        'client_id',
        'coach_id', 
        'goal_id',
        'match_score',
        'match_reasons',
        'key_alignments',
        'match_reason',
        'confidence_score',
        'presented_to_client',
        'selected_by_client',
        'selected_at'
    ];

    protected $casts = [
        'match_reasons' => 'array',
        'key_alignments' => 'array',
        'presented_to_client' => 'boolean',
        'selected_by_client' => 'boolean',
        'selected_at' => 'datetime',
        'match_score' => 'float',
        'confidence_score' => 'float',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function goal()
    {
        return $this->belongsTo(Goal::class, 'goal_id');
    }
}