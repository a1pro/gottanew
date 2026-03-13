<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoachingPackageSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('coaching_packages')->insert([
            [
                'coach_id' => 1,
                'name' => 'Quick Clarity Session',
                'description' => 'A short session to get quick clarity on your problem.',
                'duration_minutes' => 30,
                'price_amount' => 29.99,
                'price_currency' => 'USD',
                'coin_cost' => 10,
                'package_type' => 'basic',
                'features' => json_encode([
                    '30 minute coaching session',
                    'Personal guidance',
                    'Actionable next steps'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'coach_id' => 1,
                'name' => 'Deep Coaching Session',
                'description' => 'A deeper session to work on your challenges and create an action plan.',
                'duration_minutes' => 60,
                'price_amount' => 59.99,
                'price_currency' => 'USD',
                'coin_cost' => 20,
                'package_type' => 'premium',
                'features' => json_encode([
                    '60 minute coaching session',
                    'Personalized strategy',
                    'Follow-up resources'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'coach_id' => 1,
                'name' => 'VIP Transformation Session',
                'description' => 'Extended session for deep transformation and long-term planning.',
                'duration_minutes' => 90,
                'price_amount' => 99.99,
                'price_currency' => 'USD',
                'coin_cost' => 35,
                'package_type' => 'vip',
                'features' => json_encode([
                    '90 minute coaching session',
                    'Full transformation roadmap',
                    'Priority support'
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}