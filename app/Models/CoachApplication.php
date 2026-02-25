<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachApplication extends Model
{
    protected $fillable = [
        'user_id',
        'experience',
        'specialties',
        'reason',
        'certification',
        'status',
        'admin_notes',
        'reviewed_at',
        'reviewed_by'
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}