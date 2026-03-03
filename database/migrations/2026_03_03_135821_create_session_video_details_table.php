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
        Schema::create('session_video_details', function (Blueprint $table) {
        $table->id();

        $table->foreignId('session_id')
                ->constrained('coaching_sessions')
                ->cascadeOnDelete();
                
        $table->string('video_room_id')->nullable();
        $table->string('video_join_url')->nullable();
        $table->string('recording_url')->nullable();

        $table->string('daily_room_name')->nullable();
        $table->timestamp('room_created_at')->nullable();

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_video_details');
    }
};
