<?php
// app/Models/CoachProfile.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachProfile extends Model
{
    protected $fillable = [
        'user_id', 'bio', 'expertise', 'availability', 'hourly_rate', 
        'total_sessions', 'rating', 'is_approved'
    ];

    protected $casts = [
        'expertise' => 'array',
        'availability' => 'array',
        'is_approved' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}