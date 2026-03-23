<?php

namespace App\Support;

class Timezone
{
    /**
     * Canonicalize common browser or legacy timezone aliases to values Laravel/PHP accepts.
     */
    public static function normalize(?string $timezone, string $fallback = 'UTC'): string
    {
        $timezone = trim((string) $timezone);

        if ($timezone === '') {
            return $fallback;
        }

        if (in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }

        $aliases = [
            'Asia/Calcutta' => 'Asia/Kolkata',
            'US/Eastern' => 'America/New_York',
            'US/Central' => 'America/Chicago',
            'US/Mountain' => 'America/Denver',
            'US/Pacific' => 'America/Los_Angeles',
            'US/Arizona' => 'America/Phoenix',
            'Europe/Kiev' => 'Europe/Kyiv',
        ];

        $normalized = $aliases[$timezone] ?? $timezone;

        if (in_array($normalized, timezone_identifiers_list(), true)) {
            return $normalized;
        }

        return $fallback;
    }
}
