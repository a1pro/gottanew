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
            $table->foreignId('goal_id')->nullable()->constrained('goals')->onDelete('set null'); // Add goal reference
            $table->float('match_score')->default(0); // 0-10 score (to match frontend expectation)
            $table->json('match_reasons')->nullable(); // Why they matched
            $table->json('key_alignments')->nullable(); // Key alignment factors
            $table->text('match_reason')->nullable(); // Detailed match explanation
            $table->float('confidence_score')->default(0); // Confidence in the match (0-10)
            $table->boolean('presented_to_client')->default(false);
            $table->boolean('selected_by_client')->default(false);
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            // Ensure unique matches per client-coach-goal combination
            $table->unique(['client_id', 'coach_id', 'goal_id']);
            
            // Indexes for performance
            $table->index(['client_id', 'presented_to_client']);
            $table->index(['coach_id', 'match_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_matches');
    }
};