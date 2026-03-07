<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Goal\Goal;

class GoalSeeder extends Seeder
{
    public function run(): void
    {
        $goals = [

            [
                'goal_id' => 'health-fitness',
                'title' => 'Health & Fitness',
                'description' => 'Transform your physical wellbeing',
                'icon' => '🏋️',
                'color' => 'from-emerald-500 to-teal-500',
                'is_active' => 1
            ],

            [
                'goal_id' => 'career-development',
                'title' => 'Career Development',
                'description' => 'Advance your professional journey',
                'icon' => '🚀',
                'color' => 'from-blue-500 to-indigo-500',
                'is_active' => 1
            ],

            [
                'goal_id' => 'personal-growth',
                'title' => 'Personal Growth',
                'description' => 'Develop yourself mentally and emotionally',
                'icon' => '🌱',
                'color' => 'from-purple-500 to-pink-500',
                'is_active' => 1
            ],

            [
                'goal_id' => 'relationships',
                'title' => 'Relationships',
                'description' => 'Build stronger connections',
                'icon' => '❤️',
                'color' => 'from-rose-500 to-orange-500',
                'is_active' => 1
            ],

            [
                'goal_id' => 'financial-stability',
                'title' => 'Financial Stability',
                'description' => 'Secure your financial future',
                'icon' => '💰',
                'color' => 'from-yellow-500 to-green-500',
                'is_active' => 1
            ],

            [
                'goal_id' => 'creativity-hobbies',
                'title' => 'Creativity & Hobbies',
                'description' => 'Explore your passions',
                'icon' => '🎨',
                'color' => 'from-violet-500 to-purple-500',
                'is_active' => 1
            ]

        ];

        foreach ($goals as $goal) {

            Goal::updateOrCreate(
                ['goal_id' => $goal['goal_id']],
                $goal
            );

        }
    }
}