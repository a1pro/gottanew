<?php

namespace App\Services\Coach;

use App\Models\Coach\Coach;
use App\Models\Coach\CoachAvailability;
use App\Models\Session\CoachingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CoachAvailabilityService
{
    private const FIXED_DURATION_MINUTES = 15;
    public function publicAvailabilityPayload(Coach $coach): array
    {
        return [
            'coach_id' => $coach->id,
            'coach_timezone' => $coach->timezone ?: 'UTC',
            'items' => $this->activeAvailabilitiesForCoach($coach)
                ->map(fn (CoachAvailability $availability) => [
                    'id' => $availability->id,
                    'day_of_week' => (int) $availability->day_of_week,
                    'start_time' => substr((string) $availability->start_time, 0, 5),
                    'end_time' => substr((string) $availability->end_time, 0, 5),
                    'timezone' => $availability->timezone ?: ($coach->timezone ?: 'UTC'),
                    'is_active' => (bool) $availability->is_active,
                ])
                ->values()
                ->all(),
        ];
    }

    public function activeAvailabilitiesForCoach(Coach $coach): Collection
    {
        return $coach->availabilities()
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function hasAvailabilityOverlap(
        int $coachId,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
        ?int $ignoreId = null
    ): bool {
        $query = CoachAvailability::query()
            ->where('coach_id', $coachId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    public function buildSlotsForDate(Coach $coach, string $date, string $viewerTimezone): array
    {
        if (!$coach->is_active) {
            return [];
        }

        $viewerTimezone = $this->normalizeTimezone($viewerTimezone, 'UTC');
        $availabilities = $this->activeAvailabilitiesForCoach($coach);

        if ($availabilities->isEmpty()) {
            return [];
        }

        $dayStartViewer = CarbonImmutable::createFromFormat('Y-m-d H:i:s', "{$date} 00:00:00", $viewerTimezone);
        $dayEndViewer = $dayStartViewer->endOfDay();
        $nowViewer = now($viewerTimezone)->addMinutes(5);
        $rangeStartUtc = $dayStartViewer->setTimezone('UTC');
        $rangeEndUtc = $dayEndViewer->setTimezone('UTC');

        $existingSessions = $this->loadBookedSessionsForWindow($coach->id, $rangeStartUtc, $rangeEndUtc);
        $bufferMinutes = max(0, (int) ($coach->booking_buffer_minutes ?? 0));
        $slots = [];

        for ($slotStartViewer = $dayStartViewer; $slotStartViewer->lt($dayEndViewer); $slotStartViewer = $slotStartViewer->addMinutes(15)) {
            if ($slotStartViewer->lt($nowViewer)) {
                continue;
            }

            $slotEndViewer = $slotStartViewer->addMinutes(15);
            $slotStartUtc = $slotStartViewer->setTimezone('UTC');
            $slotEndUtcWithBuffer = $slotStartUtc->addMinutes(15 + $bufferMinutes);

            foreach ($availabilities as $availability) {
                $availabilityTimezone = $this->normalizeTimezone(
                    $availability->timezone ?: ($coach->timezone ?: 'UTC'),
                    $coach->timezone ?: 'UTC'
                );

                $slotStartLocal = $slotStartViewer->setTimezone($availabilityTimezone);
                $slotEndLocal = $slotEndViewer->setTimezone($availabilityTimezone);

                if ((int) $slotStartLocal->dayOfWeek !== (int) $availability->day_of_week) {
                    continue;
                }

                if ((int) $slotEndLocal->dayOfWeek !== (int) $slotStartLocal->dayOfWeek) {
                    continue;
                }

                $localStart = $slotStartLocal->format('H:i:s');
                $localEnd = $slotEndLocal->format('H:i:s');
                $availabilityStart = $this->normalizeTime((string) $availability->start_time);
                $availabilityEnd = $this->normalizeTime((string) $availability->end_time);

                if ($localStart < $availabilityStart || $localEnd > $availabilityEnd) {
                    continue;
                }

                if ($this->hasSessionConflict($existingSessions, $slotStartUtc, $slotEndUtcWithBuffer, $bufferMinutes)) {
                    continue;
                }

                $key = $slotStartUtc->timestamp;
                $slots[$key] = [
                    'starts_at' => $slotStartViewer->toIso8601String(),
                    'ends_at' => $slotEndViewer->toIso8601String(),
                    'label' => $slotStartViewer->format('H:i'),
                ];

                break;
            }
        }

        ksort($slots);

        return array_values($slots);
    }

    public function validateRequestedSlot(
        Coach $coach,
        CarbonImmutable $scheduledStart,
        string $viewerTimezone,
        int $durationMinutes = self::FIXED_DURATION_MINUTES
    ): ?string {
        if ($durationMinutes !== self::FIXED_DURATION_MINUTES) {
            return 'Only 15-minute sessions are allowed in the MVP.';
        }

        if (((int) $scheduledStart->minute % self::FIXED_DURATION_MINUTES) !== 0 || (int) $scheduledStart->second !== 0) {
            return 'Please choose a ' . self::FIXED_DURATION_MINUTES . '-minute slot using :00, :15, :30, or :45.';
        }

        $viewerTimezone = $this->normalizeTimezone($viewerTimezone, 'UTC');
        $localDate = $scheduledStart->setTimezone($viewerTimezone)->format('Y-m-d');
        $requestedTimestamp = $scheduledStart->setTimezone('UTC')->timestamp;

        $matchingSlot = collect($this->buildSlotsForDate($coach, $localDate, $viewerTimezone))
            ->first(function (array $slot) use ($requestedTimestamp) {
                return CarbonImmutable::parse($slot['starts_at'])->setTimezone('UTC')->timestamp === $requestedTimestamp;
            });

        return $matchingSlot ? null : 'Selected time is outside the coach\'s current ' . self::FIXED_DURATION_MINUTES . ' availability.';
    }

    private function loadBookedSessionsForWindow(
        int $coachId,
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc
    ): Collection {
        return CoachingSession::query()
            ->where('coach_id', $coachId)
            ->whereIn('status', ['scheduled', 'live', 'interrupted', 'in_progress'])
            ->where('scheduled_time', '>=', $rangeStartUtc->subDay()->toDateTimeString())
            ->where('scheduled_time', '<=', $rangeEndUtc->addDay()->toDateTimeString())
            ->get(['id', 'scheduled_time', 'duration_minutes', 'status']);
    }

    private function hasSessionConflict(
        Collection $existingSessions,
        CarbonImmutable $slotStartUtc,
        CarbonImmutable $slotEndUtcWithBuffer,
        int $bufferMinutes
    ): bool {
        foreach ($existingSessions as $session) {
            $existingStartUtc = CarbonImmutable::parse($session->scheduled_time)->setTimezone('UTC');
            $existingEndUtcWithBuffer = $existingStartUtc->addMinutes(((int) ($session->duration_minutes ?? 15)) + $bufferMinutes);

            if ($slotStartUtc->lt($existingEndUtcWithBuffer) && $slotEndUtcWithBuffer->gt($existingStartUtc)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeTimezone(?string $timezone, string $fallback): string
    {
        if ($timezone && in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }

        return $fallback;
    }

    private function normalizeTime(string $time): string
    {
        if (strlen($time) === 5) {
            return $time . ':00';
        }

        return $time;
    }
}
