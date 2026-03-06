<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_participants', function (Blueprint $table) {

            $table->id();

            // FIXED - must reference coaching_sessions
            $table->foreignId('session_id')
                ->constrained('coaching_sessions')
                ->cascadeOnDelete();

            // Users
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('role', ['coach','client','guest']);
            $table->string('display_name');

            $table->string('daily_user_id')->nullable();

            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('meeting_token_issued_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_participants');
    }
};