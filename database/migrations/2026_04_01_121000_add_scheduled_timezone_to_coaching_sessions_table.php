<?php

use App\Support\Timezone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaching_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('coaching_sessions', 'scheduled_timezone')) {
                $table->string('scheduled_timezone', 100)->default('UTC')->after('scheduled_time');
            }
        });

        // Best-effort backfill for existing sessions.
        DB::table('coaching_sessions')
            ->orderBy('id')
            ->chunkById(200, function ($sessions) {
                $sessionIds = collect($sessions)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->values();

                if ($sessionIds->isEmpty()) {
                    return;
                }

                $requestTimezones = DB::table('session_requests')
                    ->whereIn('approved_session_id', $sessionIds->all())
                    ->pluck('viewer_timezone', 'approved_session_id')
                    ->map(fn ($tz) => is_string($tz) ? trim($tz) : '')
                    ->all();

                $scheduledLogs = DB::table('session_state_logs')
                    ->whereIn('session_id', $sessionIds->all())
                    ->where('to_state', 'scheduled')
                    ->orderByDesc('created_at')
                    ->get(['session_id', 'metadata']);

                $logTimezones = [];
                foreach ($scheduledLogs as $row) {
                    $sid = (int) ($row->session_id ?? 0);
                    if ($sid <= 0 || isset($logTimezones[$sid])) {
                        continue;
                    }

                    $metadata = $row->metadata;
                    if (is_string($metadata)) {
                        $metadata = json_decode($metadata, true);
                    }

                    $candidate = is_array($metadata) ? (string) ($metadata['viewer_timezone'] ?? '') : '';
                    $candidate = trim($candidate);

                    if ($candidate !== '') {
                        $logTimezones[$sid] = $candidate;
                    }
                }

                foreach ($sessionIds as $sessionId) {
                    $candidate = $requestTimezones[$sessionId] ?? $logTimezones[$sessionId] ?? 'UTC';
                    $normalized = Timezone::normalize($candidate, 'UTC');

                    DB::table('coaching_sessions')
                        ->where('id', $sessionId)
                        ->update(['scheduled_timezone' => $normalized]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('coaching_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('coaching_sessions', 'scheduled_timezone')) {
                $table->dropColumn('scheduled_timezone');
            }
        });
    }
};
