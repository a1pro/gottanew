<?php
// app/Models/CoachAnalysis.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachAnalysis extends Model
{
    protected $fillable = [
        'client_id',
        'goal_id',
        'analysis',
        'total_recommendations',
        'recommendations'
    ];

    protected $casts = [
        'recommendations' => 'array'
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}