<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE pending_coach_applications
            MODIFY COLUMN status ENUM(
                'pending',
                'needs_information',
                'approved',
                'rejected',
                'invited'
            ) NOT NULL DEFAULT 'pending'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE pending_coach_applications
            MODIFY COLUMN status ENUM(
                'pending',
                'approved',
                'rejected',
                'invited'
            ) NOT NULL DEFAULT 'pending'
        ");
    }
};