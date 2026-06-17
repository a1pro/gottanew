<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $coin_amount
 * @property string $price_amount
 * @property string $price_currency
 * @property int $bonus_coins
 * @property bool $is_popular
 * @property bool $is_active
 * @property int $total_coins
 */
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

    protected $appends = [
        'total_coins',
    ];
    public function getTotalCoinsAttribute(): int
    {
        return $this->coin_amount + $this->bonus_coins;
    }
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
