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
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $coachRole = Role::firstOrCreate(['slug' => 'coach'], ['name' => 'Coach']);
        $clientRole = Role::firstOrCreate(['slug' => 'client'], ['name' => 'Client']);

        // Create admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('123456'),
                'role' => 'admin',
                'is_active' => true,
                'is_approved' => true,
                'approved_at' => now()
            ]
        );

        // Create sample coach
        $coach = User::firstOrCreate(
            ['email' => 'coach@example.com'],
            [
                'name' => 'John Coach',
                'password' => Hash::make('password'),
                'is_active' => true
            ]
        );
        if (!$coach->roles()->where('role_id', $coachRole->id)->exists()) {
            $coach->roles()->attach($coachRole);
        }
        
        CoachProfile::firstOrCreate(
            ['user_id' => $coach->id],
            [
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
            ]
        );

        // Create sample client
        $client = User::firstOrCreate(
            ['email' => 'client@example.com'],
            [
                'name' => 'Jane Client',
                'password' => Hash::make('password'),
                'is_active' => true
            ]
        );
        if (!$client->roles()->where('role_id', $clientRole->id)->exists()) {
            $client->roles()->attach($clientRole);
        }
        
        ClientProfile::firstOrCreate(
            ['user_id' => $client->id],
            [
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
            ]
        );

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
            $user = User::firstOrCreate(
                ['email' => $coachData['email']],
                [
                    'name' => $coachData['name'],
                    'password' => Hash::make('password'),
                    'is_active' => true
                ]
            );
            if (!$user->roles()->where('role_id', $coachRole->id)->exists()) {
                $user->roles()->attach($coachRole);
            }
            
            CoachProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
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
                ]
            );
        }
    }
}