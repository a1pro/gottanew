<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Finance\CurrencySetting;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
     public function run(): void
        {
            // CurrencySetting::create([
            //     'currency_code' => 'GBP',
            //     'coins_per_unit' => 10,
            //     'is_active' => true
            // ]);

            // CurrencySetting::create([
            //     'currency_code' => 'USD',
            //     'coins_per_unit' => 8,
            //     'is_active' => true
            // ]);
        }
}
