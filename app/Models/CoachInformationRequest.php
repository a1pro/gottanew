<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CoachInformationRequest extends Model
{
    protected $fillable = [
        'coach_id',
        'admin_id',
        'message',
        'coach_response',
        'attachment',
        'status',
    ];

    public function coach()
    {
        return $this->belongsTo(
            \App\Models\Coach\Coach::class,
            'coach_id'
        );
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}