<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    //

    protected $fillable = [
        'client_id',
        'coach_id',
        'scheduled_time',
        'duration_minutes',
        'status'
    ];

      protected $casts = [
        'scheduled_time' => 'datetime',
    ];

    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }
}
