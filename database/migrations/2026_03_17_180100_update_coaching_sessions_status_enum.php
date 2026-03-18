<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('coaching_sessions')
            ->where('status', 'in_progress')
            ->update(['status' => 'live']);

        DB::table('coaching_sessions')
            ->whereIn('status', ['cancelled', 'no_show'])
            ->update(['status' => 'failed']);

        DB::statement("
            ALTER TABLE coaching_sessions
            MODIFY status ENUM('scheduled','live','interrupted','completed','failed')
            NOT NULL DEFAULT 'scheduled'
        ");
    }

    public function down(): void
    {
        DB::table('coaching_sessions')
            ->where('status', 'live')
            ->update(['status' => 'in_progress']);

        DB::table('coaching_sessions')
            ->where('status', 'interrupted')
            ->update(['status' => 'cancelled']);

        DB::table('coaching_sessions')
            ->where('status', 'failed')
            ->update(['status' => 'cancelled']);

        DB::statement("
            ALTER TABLE coaching_sessions
            MODIFY status ENUM('scheduled','in_progress','completed','cancelled','no_show')
            NOT NULL DEFAULT 'scheduled'
        ");
    }
};