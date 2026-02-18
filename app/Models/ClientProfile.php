<?php
// app/Models/ClientProfile.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientProfile extends Model
{
    protected $fillable = [
        'user_id', 'goals', 'preferences', 'terms_accepted', 'terms_accepted_at'
    ];

    protected $casts = [
        'goals' => 'array',
        'preferences' => 'array',
        'terms_accepted' => 'boolean',
        'terms_accepted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}