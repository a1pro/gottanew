<?php

namespace App\Http\Controllers\Api\Finance;

use Illuminate\Http\Request;
use App\Http\Controllers\Api\BaseController;

class WalletController extends BaseController
{
    public function show(Request $request)
    {
        return $this->success($request->user()->wallet);
    }

    public function deposit(Request $request)
    {
        $wallet = $request->user()->wallet;
        $wallet->increment('coin_balance', $request->coins);

        return $this->success($wallet, 'Coins added');
    }
}