<?php

namespace App\Http\Controllers\Api\Finance;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\BaseController;
use App\Models\Finance\UserWallet;

class WalletController extends BaseController
{
    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        $wallet = $user->wallet;

        if (!$wallet) {
            $wallet = UserWallet::create([
                'user_id' => $user->id,
                'coin_balance' => 0,
                'total_coins_purchased' => 0,
                'total_coins_spent' => 0,
            ]);
        }

        return $this->success($wallet);
    }

    public function deposit(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return $this->error('Unauthenticated', 401);
        }

        $wallet = $user->wallet;

        if (!$wallet) {
            $wallet = UserWallet::create([
                'user_id' => $user->id,
                'coin_balance' => 0,
                'total_coins_purchased' => 0,
                'total_coins_spent' => 0,
            ]);
        }

        $wallet->increment('coin_balance', (int) $request->coins);

        return $this->success($wallet->fresh(), 'Coins added');
    }
}