<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class PayoutCycle extends Model
{
    protected $fillable = [
        'month_key',
        'gross_revenue_amount',
        'coach_pool_percentage',
        'coach_pool_amount',
        'platform_revenue_amount',
        'completed_purchase_count',
        'purchased_coin_amount',
        'total_completed_sessions',
        'total_session_coins',
        'status',
        'generated_by',
        'approved_at',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'gross_revenue_amount' => 'decimal:2',
        'coach_pool_percentage' => 'decimal:2',
        'coach_pool_amount' => 'decimal:2',
        'platform_revenue_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function coachPayouts()
    {
        return $this->hasMany(CoachPayout::class, 'payout_cycle_id');
    }

    public function generator()
    {
        return $this->belongsTo(\App\Models\User::class, 'generated_by');
    }
}
