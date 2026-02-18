<?php
// database/migrations/2024_01_01_000010_create_client_questionnaire_responses_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_questionnaire_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->json('goals')->nullable(); // Goal-based questions
            $table->json('personality_traits')->nullable(); // Personality questions
            $table->json('preferences')->nullable(); // Coaching preferences
            $table->boolean('completed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_questionnaire_responses');
    }
};