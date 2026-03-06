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
        Schema::create('resource_interactions', function (Blueprint $table) {
        $table->id();

        $table->foreignId('user_id')->constrained()->cascadeOnDelete();

        $table->string('resource_type');
        $table->string('resource_id');
        $table->string('resource_title')->nullable();

        $table->string('interaction_type');

        $table->integer('duration_seconds')->nullable();
        $table->decimal('completion_percentage',5,2)->nullable();
        $table->decimal('engagement_score',5,2)->nullable();

        $table->integer('feedback_rating')->nullable();
        $table->text('feedback_notes')->nullable();

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resource_interactions');
    }
};
