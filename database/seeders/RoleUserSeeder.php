<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use App\Models\CoachProfile;
use App\Models\ClientProfile;
use Illuminate\Support\Facades\Hash;

class RoleUserSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles if they don't exist (using firstOrCreate)
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin']
        );
        
        $coachRole = Role::firstOrCreate(
            ['slug' => 'coach'],
            ['name' => 'Coach']
        );
        
        $clientRole = Role::firstOrCreate(
            ['slug' => 'client'],
            ['name' => 'Client']
        );

        // Create admin user if not exists
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'is_active' => true
            ]
        );
        
        if (!$admin->roles()->where('role_id', $adminRole->id)->exists()) {
            $admin->roles()->attach($adminRole);
        }

        // Create coach user if not exists
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
        
        // Create coach profile if not exists
        if (!$coach->coachProfile) {
            CoachProfile::create([
                'user_id' => $coach->id,
                'bio' => 'Experienced life coach with 10+ years',
                'expertise' => ['Career', 'Life', 'Business'],
                'coaching_styles' => ['supportive', 'directive'],
                'hourly_rate' => 100,
                'rating' => 4.8,
                'total_sessions' => 150,
                'is_approved' => true,
                'onboarding_completed' => true
            ]);
        }

        // Create client user if not exists
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
        
        // Create client profile if not exists
        if (!$client->clientProfile) {
            ClientProfile::create([
                'user_id' => $client->id,
                'goals' => [
                    ['area' => 'Career', 'description' => 'Find better work-life balance', 'priority' => 'high'],
                    ['area' => 'Life', 'description' => 'Reduce stress and anxiety', 'priority' => 'medium']
                ],
                'terms_accepted' => true,
                'terms_accepted_at' => now(),
                'questionnaire_completed' => true
            ]);
        }

        $this->command->info('Roles and test users created successfully!');
    }
}