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
        Schema::create('goals', function (Blueprint $table) {

            $table->id();

            // unique identifier used in frontend / questionnaire
            $table->string('goal_id')->unique();

            // display title
            $table->string('title');

            // description shown in UI
            $table->text('description')->nullable();

            // emoji icon (🏋️ 🚀 ❤️ etc)
            $table->string('icon')->nullable();

            // tailwind gradient color
            $table->string('color')->nullable();

            // active / inactive goal
            $table->boolean('is_active')->default(true);

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};