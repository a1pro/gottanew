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
        Schema::table('coaches', function (Blueprint $table) {
            $table->renameColumn('interests', 'interests_and_personality');
            $table->renameColumn('favorite_challenges', 'preferred_challenges');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->renameColumn('interests_and_personality', 'interests');
            $table->renameColumn('preferred_challenges', 'favorite_challenges');
        });
    }
};