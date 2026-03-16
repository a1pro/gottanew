<?php

namespace App\Models\Session;

use Illuminate\Database\Eloquent\Model;

class SessionStateLog extends Model
{
    protected $fillable = [
        'session_id',
        'from_state',
        'to_state',
        'changed_by',
        'change_reason',
        'metadata',
    ];

    public $timestamps = false;

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(CoachingSession::class, 'session_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'changed_by');
    }
}