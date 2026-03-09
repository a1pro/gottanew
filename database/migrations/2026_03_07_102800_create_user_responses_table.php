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
       Schema::create('user_responses', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')
            ->nullable()
            ->constrained('users')
            ->cascadeOnDelete();
        $table->string('guest_session_id')->nullable();
    
        $table->foreignId('goal_id')
            ->constrained('goals');
        $table->foreignId('question_id')
            ->constrained('questions');
        $table->text('answer');
        $table->timestamps();

    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_responses');
    }
};
