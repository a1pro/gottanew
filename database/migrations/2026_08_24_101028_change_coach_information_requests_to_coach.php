<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The old pending_coach_application_id column/foreign key
        // has already been removed manually by the previous failed migration.
    
        if (!Schema::hasColumn('coach_information_requests', 'coach_id')) {
            Schema::table('coach_information_requests', function (Blueprint $table) {
                $table->foreignId('coach_id')
                    ->after('id');
            });
        }
    
        // Add the foreign key only if it does not already exist.
        $foreignKeys = collect(
            \DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'coach_information_requests'
                AND COLUMN_NAME = 'coach_id'
                AND REFERENCED_TABLE_NAME = 'coaches'
            ")
        );
    
        if ($foreignKeys->isEmpty()) {
            Schema::table('coach_information_requests', function (Blueprint $table) {
                $table->foreign('coach_id')
                    ->references('id')
                    ->on('coaches')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('coach_information_requests', 'coach_id')) {
            Schema::table('coach_information_requests', function (Blueprint $table) {
                $table->dropForeign(['coach_id']);
                $table->dropColumn('coach_id');
            });
        }
    }
};