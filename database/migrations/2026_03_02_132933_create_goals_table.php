<?php
// database/migrations/xxxx_create_goals_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
                $table->id(); // auto increment id

                $table->string('goal_id')->unique(); // slug like health-fitness

                $table->string('title');
                $table->text('description');
                $table->string('icon');
                $table->string('color');
                $table->timestamps();
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};