<?php

namespace App\Models\Finance;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TokenGrant extends Model
{
    protected $fillable = [
        'user_id',
        'granted_by_user_id',
        'coin_amount',
        'reason',
        'note',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }
}
