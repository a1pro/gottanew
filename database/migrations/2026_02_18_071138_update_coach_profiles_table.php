<?php
// database/migrations/2024_01_01_000007_update_coach_profiles_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coach_profiles', function (Blueprint $table) {
            // Add new columns for onboarding
            $table->json('coaching_styles')->nullable()->after('expertise'); // ['directive', 'supportive', 'analytical', etc]
            $table->json('availability_preferences')->nullable()->after('coaching_styles'); // Weekly schedule
            $table->boolean('ethics_acknowledged')->default(false)->after('availability_preferences');
            $table->timestamp('ethics_acknowledged_at')->nullable()->after('ethics_acknowledged');
            $table->json('boundaries')->nullable()->after('ethics_acknowledged_at'); // Coaching boundaries
            $table->boolean('onboarding_completed')->default(false)->after('boundaries');
        });
    }

    public function down(): void
    {
        Schema::table('coach_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'coaching_styles',
                'availability_preferences',
                'ethics_acknowledged',
                'ethics_acknowledged_at',
                'boundaries',
                'onboarding_completed'
            ]);
        });
    }
};