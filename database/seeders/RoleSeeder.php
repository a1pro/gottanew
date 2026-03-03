<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UserRole;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
     public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        UserRole::create([
            'user_id' => $admin->id,
            'role' => 'admin'
        ]);
    }
}
