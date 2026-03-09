<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_sessions', function (Blueprint $table) {

            $table->id();

            $table->string('session_id')->unique();

            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('goal_id')
                  ->constrained('goals')
                  ->cascadeOnDelete();

            $table->json('responses')->nullable();

            $table->json('ai_analysis')->nullable();

            $table->json('recommended_coaches')->nullable();

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_sessions');
    }
};