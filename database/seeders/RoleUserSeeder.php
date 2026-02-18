<?php
// database/seeders/RoleUserSeeder.php

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

        // Create coach
        $coach = User::create([
            'name' => 'John Coach',
            'email' => 'coach@example.com',
            'password' => Hash::make('password'),
            'is_active' => true
        ]);
        $coach->roles()->attach($coachRole);
        
        CoachProfile::create([
            'user_id' => $coach->id,
            'bio' => 'Experienced life coach with 10+ years',
            'expertise' => ['Career', 'Life', 'Business'],
            'hourly_rate' => 100,
            'rating' => 4.8,
            'is_approved' => true
        ]);

        // Create client
        $client = User::create([
            'name' => 'Jane Client',
            'email' => 'client@example.com',
            'password' => Hash::make('password'),
            'is_active' => true
        ]);
        $client->roles()->attach($clientRole);
        
        ClientProfile::create([
            'user_id' => $client->id,
            'goals' => ['Find better work-life balance', 'Career growth'],
            'terms_accepted' => true,
            'terms_accepted_at' => now()
        ]);

        // Create second coach
        $coach2 = User::create([
            'name' => 'Sarah Coach',
            'email' => 'sarah@example.com',
            'password' => Hash::make('password'),
            'is_active' => true
        ]);
        $coach2->roles()->attach($coachRole);
        
        CoachProfile::create([
            'user_id' => $coach2->id,
            'bio' => 'Wellness and mindfulness coach',
            'expertise' => ['Wellness', 'Mindfulness', 'Stress Management'],
            'hourly_rate' => 80,
            'rating' => 4.9,
            'is_approved' => true
        ]);
    }
}