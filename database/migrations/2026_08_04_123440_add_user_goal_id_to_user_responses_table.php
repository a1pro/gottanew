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
        Schema::table('user_responses', function (Blueprint $table) {

            $table->foreignId('user_goal_id')
                ->nullable()
                ->after('goal_id')
                ->constrained('user_goals')
                ->nullOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_responses', function (Blueprint $table) {

            $table->dropForeign(['user_goal_id']);
            $table->dropColumn('user_goal_id');

        });
    }
};