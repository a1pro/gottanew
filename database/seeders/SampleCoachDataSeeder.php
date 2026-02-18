<?php
// database/seeders/SampleCoachDataSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\CoachProfile;
use App\Models\CoachAvailability;

class SampleCoachDataSeeder extends Seeder
{
    public function run(): void
    {
        // Sample coaches with different expertise
        $coaches = [
            [
                'name' => 'Dr. Sarah Chen',
                'email' => 'sarah.chen@example.com',
                'bio' => 'PhD in Psychology, specializing in career transitions and work-life balance',
                'expertise' => ['Career', 'Life', 'Mental Wellness'],
                'coaching_styles' => ['supportive', 'analytical'],
                'hourly_rate' => 120,
                'rating' => 4.9,
                'total_sessions' => 345
            ],
            [
                'name' => 'Mike Thompson',
                'email' => 'mike.thompson@example.com',
                'bio' => 'Executive coach with 15 years in tech leadership. Helps founders and executives scale',
                'expertise' => ['Business', 'Leadership', 'Startup'],
                'coaching_styles' => ['directive', 'challenging'],
                'hourly_rate' => 150,
                'rating' => 4.8,
                'total_sessions' => 567
            ],
            [
                'name' => 'Elena Rodriguez',
                'email' => 'elena.rodriguez@example.com',
                'bio' => 'Mindfulness and wellness coach. Helps clients reduce stress and find purpose',
                'expertise' => ['Wellness', 'Mindfulness', 'Life'],
                'coaching_styles' => ['supportive', 'motivational'],
                'hourly_rate' => 95,
                'rating' => 4.9,
                'total_sessions' => 234
            ],
            [
                'name' => 'David Kim',
                'email' => 'david.kim@example.com',
                'bio' => 'Career coach specializing in tech transitions and skill development',
                'expertise' => ['Career', 'Technology', 'Skills'],
                'coaching_styles' => ['structured', 'analytical'],
                'hourly_rate' => 110,
                'rating' => 4.7,
                'total_sessions' => 189
            ],
            [
                'name' => 'Lisa Patel',
                'email' => 'lisa.patel@example.com',
                'bio' => 'Life coach focused on relationships, communication, and personal growth',
                'expertise' => ['Life', 'Relationships', 'Communication'],
                'coaching_styles' => ['supportive', 'reflective'],
                'hourly_rate' => 85,
                'rating' => 4.8,
                'total_sessions' => 278
            ],
            [
                'name' => 'James Wilson',
                'email' => 'james.wilson@example.com',
                'bio' => 'Business coach for entrepreneurs. Helps with strategy, operations, and growth',
                'expertise' => ['Business', 'Strategy', 'Operations'],
                'coaching_styles' => ['directive', 'structured'],
                'hourly_rate' => 130,
                'rating' => 4.6,
                'total_sessions' => 412
            ]
        ];

        $coachRole = \App\Models\Role::where('slug', 'coach')->first();

        foreach ($coaches as $coachData) {
            $user = User::create([
                'name' => $coachData['name'],
                'email' => $coachData['email'],
                'password' => bcrypt('password'),
                'is_active' => true
            ]);

            $user->roles()->attach($coachRole);

            CoachProfile::create([
                'user_id' => $user->id,
                'bio' => $coachData['bio'],
                'expertise' => $coachData['expertise'],
                'coaching_styles' => $coachData['coaching_styles'],
                'hourly_rate' => $coachData['hourly_rate'],
                'rating' => $coachData['rating'],
                'total_sessions' => $coachData['total_sessions'],
                'onboarding_completed' => true,
                'is_approved' => true,
                'ethics_acknowledged' => true,
                'ethics_acknowledged_at' => now(),
                'boundaries' => [
                    'session_duration' => 60,
                    'cancellation_policy' => '24 hours notice required',
                    'topics_to_avoid' => ['medical advice', 'legal advice']
                ]
            ]);

            // Add availability for each day
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
            foreach ($days as $day) {
                CoachAvailability::create([
                    'coach_id' => $user->id,
                    'day_of_week' => $day,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'is_available' => true
                ]);
            }
        }

        $this->command->info('Sample coaches created successfully!');
    }
}