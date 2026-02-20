<?php
// database/migrations/2024_01_01_000009_create_coach_matches_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('coach_id')->constrained('users')->onDelete('cascade');
            $table->float('match_score')->default(0); // 0-100 score
            $table->json('match_reasons')->nullable(); // Why they matched
            $table->boolean('presented_to_client')->default(false);
            $table->boolean('selected_by_client')->default(false);
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            // Ensure unique matches
            $table->unique(['client_id', 'coach_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_matches');
    }
};