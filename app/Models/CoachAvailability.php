<?php
// app/Models/CoachAvailability.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachAvailability extends Model
{
    protected $table = 'coach_availability';
    
    protected $fillable = [
        'coach_id', 'day_of_week','day', 'start_time', 'end_time', 'is_available'
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}