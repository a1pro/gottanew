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
        Schema::create('coach_availability', function (Blueprint $table) {

            $table->id();

            // Relationship with coaches table
            $table->foreignId('coach_id')
                  ->constrained('coaches')
                  ->cascadeOnDelete();

            // Day of week (0 = Sunday, 6 = Saturday)
            $table->integer('day_of_week')
                  ->comment('0=Sunday, 6=Saturday');

            $table->time('start_time');
            $table->time('end_time');

            $table->string('timezone')->default('UTC');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Optional: Prevent duplicate time slots for same coach/day
            $table->unique(['coach_id', 'day_of_week', 'start_time', 'end_time'], 'coach_availability_unique_slot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coach_availability');
    }
};