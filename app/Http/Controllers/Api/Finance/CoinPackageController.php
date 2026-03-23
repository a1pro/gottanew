<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Api\BaseController;
use App\Models\Finance\CoinPackage;
use Illuminate\Http\JsonResponse;

class CoinPackageController extends BaseController
{
    public function index(): JsonResponse
    {
        $this->ensureDefaultPackages();

        $packages = CoinPackage::query()
            ->where('is_active', true)
            ->orderByDesc('is_popular')
            ->orderBy('price_amount')
            ->get()
            ->map(function (CoinPackage $package) {
                return [
                    'id' => (string) $package->id,
                    'name' => $package->name,
                    'coin_amount' => (int) $package->coin_amount,
                    'price_amount' => (float) $package->price_amount,
                    'price_currency' => $package->price_currency,
                    'bonus_coins' => (int) $package->bonus_coins,
                    'is_popular' => (bool) $package->is_popular,
                    'is_active' => (bool) $package->is_active,
                    'total_coins' => $package->total_coins,
                ];
            })
            ->values();

        return $this->success($packages);
    }

    private function ensureDefaultPackages(): void
    {
        if (CoinPackage::query()->where('is_active', true)->exists()) {
            return;
        }

        $packages = [
            [
                'name' => 'Starter Pack',
                'coin_amount' => 1,
                'price_amount' => 15.00,
                'price_currency' => 'GBP',
                'bonus_coins' => 0,
                'is_popular' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Growth Pack',
                'coin_amount' => 3,
                'price_amount' => 42.00,
                'price_currency' => 'GBP',
                'bonus_coins' => 1,
                'is_popular' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Momentum Pack',
                'coin_amount' => 6,
                'price_amount' => 78.00,
                'price_currency' => 'GBP',
                'bonus_coins' => 2,
                'is_popular' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Transformation Pack',
                'coin_amount' => 12,
                'price_amount' => 150.00,
                'price_currency' => 'GBP',
                'bonus_coins' => 4,
                'is_popular' => false,
                'is_active' => true,
            ],
        ];

        foreach ($packages as $package) {
            CoinPackage::query()->updateOrCreate(
                ['name' => $package['name']],
                $package
            );
        }
    }
}
