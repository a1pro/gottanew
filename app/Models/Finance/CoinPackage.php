<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class CoinPackage extends Model
{
    protected $fillable = [
        'name',
        'coin_amount',
        'price_amount',
        'price_currency',
        'bonus_coins',
        'is_popular',
        'is_active',
    ];

    protected $casts = [
        'coin_amount' => 'integer',
        'price_amount' => 'decimal:2',
        'bonus_coins' => 'integer',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function getTotalCoinsAttribute(): int
    {
        return (int) $this->coin_amount + (int) $this->bonus_coins;
    }
}
