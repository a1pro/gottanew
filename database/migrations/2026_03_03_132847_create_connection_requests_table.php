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
     Schema::create('connection_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('coach_id')->constrained()->cascadeOnDelete();

    $table->enum('status',['pending','accepted','declined','expired'])
          ->default('pending');

    $table->json('client_goal')->nullable();
    $table->text('client_bio')->nullable();

    $table->enum('request_type',['instant','scheduled'])->default('instant');
    $table->timestamp('scheduled_time')->nullable();

    $table->string('video_link')->nullable();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connection_requests');
    }
};
