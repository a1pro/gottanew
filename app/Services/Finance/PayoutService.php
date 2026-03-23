<?php

namespace App\Services\Finance;

use App\Models\Coach\Coach;
use App\Models\Finance\CoachPayout;
use App\Models\Finance\PayoutCycle;
use App\Models\Finance\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    public const COACH_POOL_PERCENTAGE = 80.0;

    public function normalizeMonth(?string $month): Carbon
    {
        if (filled($month)) {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        }

        return now()->startOfMonth();
    }

    public function buildMonthSnapshot(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $purchaseStats = Transaction::query()
            ->where('transaction_type', 'coin_purchase')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) as purchase_count')
            ->selectRaw('COALESCE(SUM(amount_fiat), 0) as gross_revenue')
            ->selectRaw('COALESCE(SUM(coin_amount), 0) as purchased_coin_amount')
            ->first();

        $grossRevenue = round((float) ($purchaseStats->gross_revenue ?? 0), 2);
        $coachPoolAmount = round($grossRevenue * (self::COACH_POOL_PERCENTAGE / 100), 2);
        $platformRevenueAmount = round($grossRevenue - $coachPoolAmount, 2);

        $sessionStats = DB::table('coaching_sessions')
            ->join('coaches', 'coaches.id', '=', 'coaching_sessions.coach_id')
            ->leftJoin('users', 'users.id', '=', 'coaches.user_id')
            ->where('coaching_sessions.status', 'completed')
            ->whereBetween('coaching_sessions.scheduled_time', [$start, $end])
            ->groupBy('coaching_sessions.coach_id', 'coaches.name', 'users.email')
            ->selectRaw('coaching_sessions.coach_id')
            ->selectRaw('coaches.name as coach_name')
            ->selectRaw('users.email as coach_email')
            ->selectRaw('COUNT(coaching_sessions.id) as completed_sessions_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN coaching_sessions.price_amount IS NULL OR coaching_sessions.price_amount = 0 THEN 1 ELSE coaching_sessions.price_amount END), 0) as session_coin_amount')
            ->orderByDesc('session_coin_amount')
            ->get();

        $totalCompletedSessions = (int) $sessionStats->sum('completed_sessions_count');
        $totalSessionCoins = (int) $sessionStats->sum('session_coin_amount');

        $coachBreakdown = $sessionStats
            ->map(function ($row) use ($totalSessionCoins, $coachPoolAmount) {
                $sessionCoinAmount = (int) $row->session_coin_amount;
                $sharePercentage = $totalSessionCoins > 0
                    ? round(($sessionCoinAmount / $totalSessionCoins) * 100, 4)
                    : 0.0;

                $payoutAmount = $totalSessionCoins > 0
                    ? round(($sessionCoinAmount / $totalSessionCoins) * $coachPoolAmount, 2)
                    : 0.0;

                return [
                    'coach_id' => (int) $row->coach_id,
                    'coach_name' => $row->coach_name,
                    'coach_email' => $row->coach_email,
                    'completed_sessions_count' => (int) $row->completed_sessions_count,
                    'session_coin_amount' => $sessionCoinAmount,
                    'payout_share_percentage' => $sharePercentage,
                    'payout_amount' => $payoutAmount,
                ];
            })
            ->values()
            ->all();

        return [
            'month' => $start->format('Y-m'),
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'gross_revenue_amount' => $grossRevenue,
            'coach_pool_percentage' => self::COACH_POOL_PERCENTAGE,
            'coach_pool_amount' => $coachPoolAmount,
            'platform_revenue_amount' => $platformRevenueAmount,
            'completed_purchase_count' => (int) ($purchaseStats->purchase_count ?? 0),
            'purchased_coin_amount' => (int) ($purchaseStats->purchased_coin_amount ?? 0),
            'total_completed_sessions' => $totalCompletedSessions,
            'total_session_coins' => $totalSessionCoins,
            'coach_breakdown' => $coachBreakdown,
        ];
    }

    public function generateCycle(Carbon $month, int $generatedByUserId): PayoutCycle
    {
        $snapshot = $this->buildMonthSnapshot($month);
        $monthKey = $month->format('Y-m');

        return DB::transaction(function () use ($snapshot, $generatedByUserId, $monthKey) {
            $cycle = PayoutCycle::query()->firstOrNew(['month_key' => $monthKey]);

            if ($cycle->exists && $cycle->status === 'paid') {
                throw new \RuntimeException('This payout cycle is already marked as paid and cannot be regenerated.');
            }

            $cycle->fill([
                'gross_revenue_amount' => $snapshot['gross_revenue_amount'],
                'coach_pool_percentage' => $snapshot['coach_pool_percentage'],
                'coach_pool_amount' => $snapshot['coach_pool_amount'],
                'platform_revenue_amount' => $snapshot['platform_revenue_amount'],
                'completed_purchase_count' => $snapshot['completed_purchase_count'],
                'purchased_coin_amount' => $snapshot['purchased_coin_amount'],
                'total_completed_sessions' => $snapshot['total_completed_sessions'],
                'total_session_coins' => $snapshot['total_session_coins'],
                'generated_by' => $generatedByUserId,
                'status' => 'draft',
                'approved_at' => null,
                'paid_at' => null,
            ]);
            $cycle->save();

            $cycle->coachPayouts()->delete();

            $payoutRows = collect($snapshot['coach_breakdown'])
                ->map(function (array $row) use ($cycle) {
                    return [
                        'payout_cycle_id' => $cycle->id,
                        'coach_id' => $row['coach_id'],
                        'completed_sessions_count' => $row['completed_sessions_count'],
                        'session_coin_amount' => $row['session_coin_amount'],
                        'payout_share_percentage' => $row['payout_share_percentage'],
                        'payout_amount' => $row['payout_amount'],
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })
                ->all();

            if (!empty($payoutRows)) {
                CoachPayout::query()->insert($payoutRows);
            }

            return $cycle->fresh(['coachPayouts.coach.user']);
        });
    }

    public function approveCycle(PayoutCycle $cycle): PayoutCycle
    {
        return DB::transaction(function () use ($cycle) {
            if ($cycle->status === 'paid') {
                throw new \RuntimeException('This payout cycle is already paid.');
            }

            $cycle->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            $cycle->coachPayouts()
                ->where('status', '!=', 'paid')
                ->update([
                    'status' => 'approved',
                    'updated_at' => now(),
                ]);

            return $cycle->fresh(['coachPayouts.coach.user']);
        });
    }

    public function markCyclePaid(PayoutCycle $cycle, ?string $reference = null): PayoutCycle
    {
        return DB::transaction(function () use ($cycle, $reference) {
            if ($cycle->status === 'paid') {
                return $cycle->fresh(['coachPayouts.coach.user']);
            }

            $cycle->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $cycle->coachPayouts()->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payout_reference' => $reference,
                'updated_at' => now(),
            ]);

            return $cycle->fresh(['coachPayouts.coach.user']);
        });
    }

    public function serializeCycle(PayoutCycle $cycle): array
    {
        $cycle->loadMissing(['coachPayouts.coach.user']);

        return [
            'id' => $cycle->id,
            'month_key' => $cycle->month_key,
            'gross_revenue_amount' => (float) $cycle->gross_revenue_amount,
            'coach_pool_percentage' => (float) $cycle->coach_pool_percentage,
            'coach_pool_amount' => (float) $cycle->coach_pool_amount,
            'platform_revenue_amount' => (float) $cycle->platform_revenue_amount,
            'completed_purchase_count' => (int) $cycle->completed_purchase_count,
            'purchased_coin_amount' => (int) $cycle->purchased_coin_amount,
            'total_completed_sessions' => (int) $cycle->total_completed_sessions,
            'total_session_coins' => (int) $cycle->total_session_coins,
            'status' => $cycle->status,
            'approved_at' => optional($cycle->approved_at)->toISOString(),
            'paid_at' => optional($cycle->paid_at)->toISOString(),
            'notes' => $cycle->notes,
            'coach_payouts' => $cycle->coachPayouts
                ->sortByDesc('payout_amount')
                ->values()
                ->map(function (CoachPayout $payout) {
                    return $this->serializeCoachPayout($payout);
                })
                ->all(),
        ];
    }

    public function serializeCoachPayout(CoachPayout $payout): array
    {
        $coach = $payout->coach;

        return [
            'id' => $payout->id,
            'coach_id' => $payout->coach_id,
            'coach_name' => $coach?->name,
            'coach_email' => $coach?->user?->email,
            'completed_sessions_count' => (int) $payout->completed_sessions_count,
            'session_coin_amount' => (int) $payout->session_coin_amount,
            'payout_share_percentage' => (float) $payout->payout_share_percentage,
            'payout_amount' => (float) $payout->payout_amount,
            'status' => $payout->status,
            'paid_at' => optional($payout->paid_at)->toISOString(),
            'payout_reference' => $payout->payout_reference,
            'notes' => $payout->notes,
        ];
    }

    public function coachEarningsData(int $userId, ?string $month = null): array
    {
        $coach = Coach::query()->where('user_id', $userId)->firstOrFail();
        $selectedMonth = $this->normalizeMonth($month);
        $monthKey = $selectedMonth->format('Y-m');

        $cycle = PayoutCycle::query()
            ->with(['coachPayouts' => function ($query) use ($coach) {
                $query->where('coach_id', $coach->id)->with('coach.user');
            }])
            ->where('month_key', $monthKey)
            ->first();

        $history = CoachPayout::query()
            ->with(['payoutCycle', 'coach.user'])
            ->where('coach_id', $coach->id)
            ->get()
            ->sortByDesc(fn (CoachPayout $payout) => $payout->payoutCycle?->month_key)
            ->values()
            ->map(function (CoachPayout $payout) {
                return array_merge(
                    $this->serializeCoachPayout($payout),
                    [
                        'month_key' => $payout->payoutCycle?->month_key,
                        'cycle_status' => $payout->payoutCycle?->status,
                    ]
                );
            })
            ->all();

        return [
            'coach' => [
                'id' => $coach->id,
                'name' => $coach->name,
                'email' => $coach->user?->email,
            ],
            'month' => $monthKey,
            'selected_payout' => $cycle && $cycle->coachPayouts->isNotEmpty()
                ? array_merge(
                    $this->serializeCoachPayout($cycle->coachPayouts->first()),
                    [
                        'month_key' => $cycle->month_key,
                        'cycle_status' => $cycle->status,
                    ]
                )
                : null,
            'history' => $history,
            'totals' => [
                'total_payout_amount' => round((float) collect($history)->sum('payout_amount'), 2),
                'total_session_coins' => (int) collect($history)->sum('session_coin_amount'),
                'total_completed_sessions' => (int) collect($history)->sum('completed_sessions_count'),
                'paid_count' => (int) collect($history)->where('status', 'paid')->count(),
                'pending_count' => (int) collect($history)->whereIn('status', ['pending', 'approved'])->count(),
            ],
        ];
    }
}
