<?php
 
namespace Database\Seeders;
 
use Illuminate\Database\Seeder;
use App\Models\Goal;
 
class GoalsSeeder extends Seeder
{
    public function run(): void
    {
        $goals = [
            [
                'goal_id' => 'health-fitness', // Use goal_id column
                'title' => 'Health & Fitness',
                'description' => 'Transform your physical wellbeing and build lasting healthy habits',
                'icon' => '🏋️',
                'color' => 'from-emerald-500 to-teal-500'
            ],
            [
                'goal_id' => 'career-development',
                'title' => 'Career Development',
                'description' => 'Advance your professional journey and unlock new opportunities',
                'icon' => '🚀',
                'color' => 'from-blue-500 to-indigo-500'
            ],
            [
                'goal_id' => 'personal-growth',
                'title' => 'Personal Growth',
                'description' => 'Develop yourself mentally, emotionally, and spiritually',
                'icon' => '🌱',
                'color' => 'from-purple-500 to-pink-500'
            ],
            [
                'goal_id' => 'relationships',
                'title' => 'Relationships',
                'description' => 'Build stronger connections and improve communication',
                'icon' => '❤️',
                'color' => 'from-rose-500 to-orange-500'
            ],
            [
                'goal_id' => 'financial-stability',
                'title' => 'Financial Stability',
                'description' => 'Take control of your finances and secure your future',
                'icon' => '💰',
                'color' => 'from-yellow-500 to-green-500'
            ],
            [
                'goal_id' => 'creativity-hobbies',
                'title' => 'Creativity & Hobbies',
                'description' => 'Creating time to explore value through creative pursuits',
                'icon' => '🎨',
                'color' => 'from-violet-500 to-purple-500'
            ]
        ];
 
        foreach ($goals as $goal) {
            Goal::create($goal);
        }
 
        $this->command->info('Goals seeded successfully!');
    }
}