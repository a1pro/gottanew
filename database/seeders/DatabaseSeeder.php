<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use App\Models\CoachProfile;
use App\Models\ClientProfile;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $coachRole = Role::create(['name' => 'Coach', 'slug' => 'coach']);
        $clientRole = Role::create(['name' => 'Client', 'slug' => 'client']);

        // Create admin
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'is_active' => true
        ]);
        $admin->roles()->attach($adminRole);

        // Create sample coach
        $coach = User::create([
            'name' => 'John Coach',
            'email' => 'coach@example.com',
            'password' => Hash::make('password'),
            'is_active' => true
        ]);
        $coach->roles()->attach($coachRole);
        
        CoachProfile::create([
            'user_id' => $coach->id,
            'bio' => 'Experienced life coach with 10+ years helping people achieve their goals.',
            'expertise' => ['Career', 'Life', 'Business'],
            'coaching_styles' => ['supportive', 'directive'],
            'hourly_rate' => 100,
            'languages' => ['English', 'Spanish'],
            'certifications' => ['Certified Life Coach', 'NLP Practitioner'],
            'education' => ['Masters in Psychology'],
            'experience_years' => 10,
            'rating' => 4.8,
            'total_sessions' => 150,
            'is_approved' => true,
            'onboarding_completed' => true
        ]);

        // Create sample client
        $client = User::create([
            'name' => 'Jane Client',
            'email' => 'client@example.com',
            'password' => Hash::make('password'),
            'is_active' => true
        ]);
        $client->roles()->attach($clientRole);
        
        ClientProfile::create([
            'user_id' => $client->id,
            'goals' => [
                ['area' => 'Career', 'description' => 'Find better work-life balance', 'priority' => 'high'],
                ['area' => 'Life', 'description' => 'Reduce stress and anxiety', 'priority' => 'medium']
            ],
            'personality_traits' => [
                'communication_style' => 'direct',
                'decision_making' => 'logical',
                'challenge_readiness' => 7,
                'support_preference' => 'guidance'
            ],
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
            'questionnaire_completed' => true
        ]);

        // Create additional sample coaches
        $coaches = [
            [
                'name' => 'Sarah Chen',
                'email' => 'sarah@example.com',
                'bio' => 'Career coach specializing in tech transitions',
                'expertise' => ['Career', 'Technology'],
                'styles' => ['analytical', 'structured'],
                'rate' => 120
            ],
            [
                'name' => 'Mike Thompson',
                'email' => 'mike@example.com',
                'bio' => 'Executive coach for business leaders',
                'expertise' => ['Business', 'Leadership'],
                'styles' => ['directive', 'challenging'],
                'rate' => 150
            ],
            [
                'name' => 'Elena Rodriguez',
                'email' => 'elena@example.com',
                'bio' => 'Wellness and mindfulness coach',
                'expertise' => ['Wellness', 'Mindfulness'],
                'styles' => ['supportive', 'reflective'],
                'rate' => 90
            ]
        ];

        foreach ($coaches as $coachData) {
            $user = User::create([
                'name' => $coachData['name'],
                'email' => $coachData['email'],
                'password' => Hash::make('password'),
                'is_active' => true
            ]);
            $user->roles()->attach($coachRole);
            
            CoachProfile::create([
                'user_id' => $user->id,
                'bio' => $coachData['bio'],
                'expertise' => $coachData['expertise'],
                'coaching_styles' => $coachData['styles'],
                'hourly_rate' => $coachData['rate'],
                'languages' => ['English'],
                'experience_years' => 8,
                'rating' => 4.7,
                'total_sessions' => 100,
                'is_approved' => true,
                'onboarding_completed' => true
            ]);
        }
    }
}