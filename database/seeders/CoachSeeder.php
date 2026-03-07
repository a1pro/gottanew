<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Coach\Coach;
use App\Models\Core\UserRole;

class CoachSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $coachUser = User::factory()->create([
        //     'name' => 'John Coach',
        //     'email' => 'coach@example.com'
        // ]);

        // UserRole::create([
        //     'user_id' => $coachUser->id,
        //     'role' => 'coach'
        // ]);

        // Coach::create([
        //     'user_id' => $coachUser->id,
        //     'name' => 'John Coach',
        //     'title' => 'Life Coach',
        //     'bio' => 'Helping you unlock your potential.',
        //     'years_experience' => 5,
        //     'specialties' => json_encode(['career','mindset']),
        //     'similar_experiences' => json_encode(['career growth','burnout recovery']),
        //     'rating' => 5,
        //     'total_reviews' => 0
        // ]);
    }
}
