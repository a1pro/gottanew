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
        Schema::create('conversation_themes', function (Blueprint $table) {
        $table->id();

        $table->foreignId('user_id')->constrained()->cascadeOnDelete();

        $table->string('theme_name');
        $table->text('theme_description')->nullable();

        $table->timestamp('first_mentioned_at')->useCurrent();
        $table->timestamp('last_mentioned_at')->useCurrent();

        $table->integer('mention_count')->default(1);

        $table->json('sentiment_trend')->nullable();
        $table->decimal('importance_score',5,2)->nullable();

        $table->json('related_goals')->nullable();
        $table->json('session_ids')->nullable();

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_themes');
    }
};
