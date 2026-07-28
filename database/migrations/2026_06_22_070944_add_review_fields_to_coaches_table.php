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

            $table->string('status')
                ->default('pending_review')
                ->after('available_now');

            $table->boolean('is_verified')
                ->default(false)
                ->after('status');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table) {

            $table->dropColumn([
                'status',
                'is_verified'
            ]);

        });
    }

};
