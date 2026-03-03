<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_tasks', function (Blueprint $table) {

            $table->id();

            // Users
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Goals
            $table->foreignId('goal_id')
                ->nullable()
                ->constrained('user_goals')
                ->nullOnDelete();

            // Sessions (FIXED HERE)
            $table->foreignId('session_id')
                ->nullable()
                ->constrained('coaching_sessions')   // IMPORTANT FIX
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->boolean('is_completed')->default(false);

            $table->timestamp('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->enum('priority',['low','medium','high'])
                ->default('medium');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tasks');
    }
};