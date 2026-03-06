<?php

namespace App\Models\Coach;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class PendingCoachApplication extends Model
{
    protected $table = 'pending_coach_applications';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'experience',
        'specialties',
        'message',
        'status',
        'reviewed_by',
        'reviewed_at'
    ];

    protected $casts = [
        'specialties' => 'array',
        'reviewed_at' => 'datetime'
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

}