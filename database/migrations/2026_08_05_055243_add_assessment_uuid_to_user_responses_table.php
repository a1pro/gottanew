<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_responses', function (Blueprint $table) {

            $table->uuid('assessment_uuid')
                ->nullable()
                ->after('guest_session_id')
                ->index();

        });
    }

    public function down(): void
    {
        Schema::table('user_responses', function (Blueprint $table) {

            $table->dropColumn('assessment_uuid');

        });
    }
};