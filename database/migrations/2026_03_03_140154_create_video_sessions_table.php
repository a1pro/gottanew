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
        Schema::create('video_sessions', function (Blueprint $table) {
        $table->id();

        $table->foreignId('connection_request_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->string('session_id');

        $table->enum('status',['scheduled','active','completed','cancelled'])
            ->default('scheduled');

        $table->integer('duration_minutes')->default(0);
        $table->integer('overtime_minutes')->default(0);

        $table->string('recording_url')->nullable();
        $table->longText('transcript')->nullable();

        $table->timestamp('started_at')->nullable();
        $table->timestamp('ended_at')->nullable();

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_sessions');
    }
};
