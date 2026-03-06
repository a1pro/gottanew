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
        Schema::create('user_behavioral_patterns', function (Blueprint $table) {
        $table->id();

        $table->foreignId('user_id')->constrained()->cascadeOnDelete();

        $table->string('pattern_type');
        $table->json('pattern_data');

        $table->decimal('confidence_score',5,2)->nullable();

        $table->timestamp('identified_at')->useCurrent();
        $table->timestamp('last_reinforced_at')->useCurrent();

        $table->integer('occurrence_count')->default(1);

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_behavioral_patterns');
    }
};
