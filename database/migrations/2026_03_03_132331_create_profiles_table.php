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
        Schema::create('profiles', function (Blueprint $table) {

            $table->id();

            // Relationship with users table (1:1)
            $table->foreignId('user_id')
                  ->unique()
                  ->constrained()
                  ->cascadeOnDelete();

            // Basic Info
            $table->string('full_name')->nullable();
            $table->text('bio')->nullable();
            $table->string('phone')->nullable();
            $table->enum('notification_method', ['email', 'whatsapp', 'both'])
                  ->default('email');
            $table->boolean('email_verified')->default(false);

            // JSON Fields
            $table->json('personality_traits')->nullable();
            $table->json('communication_style')->nullable();
            $table->json('engagement_patterns')->nullable();
            $table->json('learning_preferences')->nullable();
            $table->json('motivation_triggers')->nullable();
            $table->json('success_patterns')->nullable();
            $table->json('preferred_session_times')->nullable();

            // Coaching Stats
            $table->text('coaching_history_summary')->nullable();
            $table->integer('total_sessions_count')->default(0);
            $table->decimal('average_session_rating', 3, 2)->nullable();
            $table->timestamp('last_session_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};