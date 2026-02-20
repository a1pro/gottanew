<?php
// database/migrations/2026_02_19_000001_create_coach_profiles_table_fixed.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('bio')->nullable();
            $table->json('expertise')->nullable();
            $table->json('coaching_styles')->nullable();
            $table->json('availability_preferences')->nullable(); // Moved up, no AFTER clause needed
            $table->boolean('ethics_acknowledged')->default(false);
            $table->timestamp('ethics_acknowledged_at')->nullable();
            $table->json('boundaries')->nullable();
            $table->integer('hourly_rate')->nullable();
            $table->json('languages')->nullable();
            $table->json('certifications')->nullable();
            $table->json('education')->nullable();
            $table->integer('experience_years')->nullable();
            $table->float('rating')->default(0);
            $table->integer('total_sessions')->default(0);
            $table->boolean('is_approved')->default(false);
            $table->boolean('onboarding_completed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_profiles');
    }
};