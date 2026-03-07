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
        Schema::create('questions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('goal_id')
                ->constrained('goals')
                ->cascadeOnDelete();

            $table->text('question');
            
            $table->enum('type', [
                'multiple-choice',
                'open-ended',
                'scale'
            ])->default('multiple-choice');

          $table->string('placeholder')->nullable();


            $table->integer('order')->default(1);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
