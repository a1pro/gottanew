<?php
namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id','coach_id','package_id',
        'transaction_type','amount_currency',
        'amount_fiat','coin_amount',
        'stripe_session_id','status'
    ];
}