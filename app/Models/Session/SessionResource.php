<?php

namespace App\Models\Session;

use Illuminate\Database\Eloquent\Model;

class SessionResource extends Model
{
    protected $fillable = [
        'session_id',
        'created_by',
        'resource_type',
        'title',
        'url',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function session()
    {
        return $this->belongsTo(CoachingSession::class, 'session_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
