<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class CoachPayout extends Model
{
    protected $fillable = [
        'payout_cycle_id',
        'coach_id',
        'completed_sessions_count',
        'session_coin_amount',
        'payout_share_percentage',
        'payout_amount',
        'status',
        'paid_at',
        'payout_reference',
        'notes',
    ];

    protected $casts = [
        'payout_share_percentage' => 'decimal:4',
        'payout_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function payoutCycle()
    {
        return $this->belongsTo(PayoutCycle::class, 'payout_cycle_id');
    }

    public function coach()
    {
        return $this->belongsTo(\App\Models\Coach\Coach::class, 'coach_id');
    }
}
