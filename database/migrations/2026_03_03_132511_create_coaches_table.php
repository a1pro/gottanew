<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coaches', function (Blueprint $table) {

            $table->id();

            // Optional relation with users table
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            // Basic Info
            $table->string('name');
            $table->string('title');
            $table->text('bio');
            $table->string('avatar_url')->nullable();

            // Experience & Skills
            $table->integer('years_experience');
            $table->json('specialties');
            $table->json('similar_experiences');

            // Ratings
            $table->decimal('rating', 3, 2)->default(5.00);
            $table->integer('total_reviews')->default(0);

            // Availability
            $table->string('availability_hours')->nullable();
            $table->string('timezone')->default('UTC');
            $table->json('social_links')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('available_now')->default(false);

            // Contact & Calendar
            $table->string('calendar_link')->nullable();
            $table->string('notification_email')->nullable();
            $table->string('notification_phone')->nullable();

            // Coaching Details
            $table->text('coaching_expertise')->nullable();
            $table->text('coaching_style')->nullable();
            $table->text('client_challenge_example')->nullable();
            $table->text('personal_experiences')->nullable();

            // Pricing
            $table->decimal('hourly_rate_amount', 10, 2)->default(100.00);
            $table->string('hourly_rate_currency')->default('USD');
            $table->integer('hourly_coin_cost')->default(100);

            // Session Rules
            $table->integer('booking_buffer_minutes')->default(15);
            $table->integer('max_session_duration')->default(60);
            $table->integer('min_session_duration')->default(30);

            $table->boolean('immediate_availability')->default(true);
            $table->integer('response_preference_minutes')->default(5);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coaches');
    }
};