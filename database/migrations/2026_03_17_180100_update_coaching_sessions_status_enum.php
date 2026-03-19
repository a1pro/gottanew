<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Expand enum to allow BOTH the old and new statuses
        DB::statement("
            ALTER TABLE coaching_sessions
            MODIFY status ENUM(
                'scheduled',
                'in_progress',
                'live',
                'completed',
                'cancelled',
                'no_show'
            ) NOT NULL DEFAULT 'scheduled'
        ");

        // 2) Migrate existing rows (proper quoting handled by query builder)
        DB::table('coaching_sessions')
            ->where('status', 'in_progress')
            ->update(['status' => 'live']);

        // 3) Optional: shrink enum to drop 'in_progress' once no rows use it
        DB::statement("
            ALTER TABLE coaching_sessions
            MODIFY status ENUM(
                'scheduled',
                'live',
                'completed',
                'cancelled',
                'no_show'
            ) NOT NULL DEFAULT 'scheduled'
        ");
    }

    public function down(): void
    {
        // Reverse (if you need it): expand to include both, convert back, then shrink.
        DB::statement("
            ALTER TABLE coaching_sessions
            MODIFY status ENUM(
                'scheduled',
                'in_progress',
                'live',
                'completed',
                'cancelled',
                'no_show'
            ) NOT NULL DEFAULT 'scheduled'
        ");

        DB::table('coaching_sessions')
            ->where('status', 'live')
            ->update(['status' => 'in_progress']);

        DB::statement("
            ALTER TABLE coaching_sessions
            MODIFY status ENUM(
                'scheduled',
                'in_progress',
                'completed',
                'cancelled',
                'no_show'
            ) NOT NULL DEFAULT 'scheduled'
        ");
    }
};
