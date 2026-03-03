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
        Schema::create('user_activity_logs', function (Blueprint $table) {
        $table->id();

        $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

        $table->string('session_token')->nullable();
        $table->string('activity_type');
        $table->string('page_url')->nullable();

        $table->json('action_details')->nullable();
        $table->integer('duration_seconds')->nullable();
        $table->json('metadata')->nullable();

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};
