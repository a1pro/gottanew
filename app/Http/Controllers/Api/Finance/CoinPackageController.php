<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Api\BaseController;
use App\Models\Finance\CoinPackage;
use Illuminate\Http\JsonResponse;

class CoinPackageController extends BaseController
{
    public function index(): JsonResponse
    {

        $packages = CoinPackage::query()
            ->active()
            ->orderByDesc('is_popular')
            ->orderBy('price_amount')
            ->get()
            ->map(function (CoinPackage $package) {
                return [
                    'id'             =>  $package->id,
                    'name'           =>  $package->name,
                    'coin_amount'    =>  $package->coin_amount,
                    'price_amount'   =>  $package->price_amount,
                    'price_currency' =>  $package->price_currency,
                    'bonus_coins'    =>  $package->bonus_coins,
                    'is_popular'     =>  $package->is_popular,
                    'is_active'      =>  $package->is_active,
                    'total_coins'    =>  $package->total_coins,

                ];
            })
            ->values();

        return $this->success($packages);
    }
}
