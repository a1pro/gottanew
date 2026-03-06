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
       Schema::create('session_recordings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('session_id')
                ->constrained('coaching_sessions')
                ->cascadeOnDelete();

            $table->string('recording_url')->nullable();
            $table->longText('transcript')->nullable();
            $table->longText('ai_summary')->nullable();

            $table->integer('duration_seconds')->nullable();
            $table->bigInteger('file_size_bytes')->nullable();

            $table->json('sentiment_analysis')->nullable();
            $table->json('key_topics')->nullable();
            $table->json('personality_insights')->nullable();
            $table->json('emotional_journey')->nullable();

            $table->decimal('coaching_effectiveness_score',5,2)->nullable();

            $table->enum('transcription_status',['inactive','active','paused','completed'])
                ->default('inactive');

            $table->json('transcription_paused_segments')->nullable();
            $table->json('privacy_settings')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_recordings');
    }
};
