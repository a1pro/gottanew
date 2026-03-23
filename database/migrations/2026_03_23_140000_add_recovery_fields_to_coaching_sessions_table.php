<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("\n            ALTER TABLE coaching_sessions\n            MODIFY status ENUM(\n                'scheduled',\n                'live',\n                'interrupted',\n                'completed',\n                'failed',\n                'cancelled',\n                'no_show'\n            ) NOT NULL DEFAULT 'scheduled'\n        ");

        DB::table('coaching_sessions')
            ->where('status', 'cancelled')
            ->update(['status' => 'failed']);

        DB::table('coaching_sessions')
            ->where('status', 'no_show')
            ->update(['status' => 'failed']);

        Schema::table('coaching_sessions', function (Blueprint $table) {
            $table->timestamp('actual_started_at')->nullable()->after('scheduled_time');
            $table->timestamp('actual_ended_at')->nullable()->after('actual_started_at');
            $table->timestamp('last_activity_at')->nullable()->after('actual_ended_at');
            $table->timestamp('last_interrupted_at')->nullable()->after('last_activity_at');
            $table->timestamp('recovery_deadline_at')->nullable()->after('last_interrupted_at');
            $table->unsignedInteger('recovery_attempts')->default(0)->after('recovery_deadline_at');
            $table->string('failure_reason')->nullable()->after('recovery_attempts');
            $table->json('recovery_context')->nullable()->after('failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('coaching_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'actual_started_at',
                'actual_ended_at',
                'last_activity_at',
                'last_interrupted_at',
                'recovery_deadline_at',
                'recovery_attempts',
                'failure_reason',
                'recovery_context',
            ]);
        });

        DB::table('coaching_sessions')
            ->where('status', 'failed')
            ->update(['status' => 'cancelled']);

        DB::statement("\n            ALTER TABLE coaching_sessions\n            MODIFY status ENUM(\n                'scheduled',\n                'live',\n                'completed',\n                'cancelled',\n                'no_show'\n            ) NOT NULL DEFAULT 'scheduled'\n        ");
    }
};
