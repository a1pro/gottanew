<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Core\UserRole;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Create or get admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'], // search condition
            [
                'name' => 'Admin User',
                'password' => Hash::make('Admin@123'),
            ]
        );

        // Assign role if not already assigned
        UserRole::firstOrCreate(
            [
                'user_id' => $admin->id,
                'role' => 'admin'
            ]
        );

    }
}