<?php
namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class UserWallet extends Model
{
    protected $fillable = [
        'user_id','coin_balance',
        'total_coins_purchased','total_coins_spent'
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}