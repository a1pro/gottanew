<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\Finance\PayoutCycle;
use App\Services\Finance\PayoutService;
use App\Services\Communication\NotificationService;
use Illuminate\Http\Request;

class PayoutController extends BaseController
{
    public function __construct(
        private readonly PayoutService $payoutService,
        private readonly NotificationService $notificationService
    ) {
    }

    public function overview(Request $request)
    {
        $month = $this->payoutService->normalizeMonth($request->get('month'));
        $monthKey = $month->format('Y-m');

        $cycle = PayoutCycle::query()
            ->with(['coachPayouts.coach.user'])
            ->where('month_key', $monthKey)
            ->first();

        $snapshot = $this->payoutService->buildMonthSnapshot($month);

        return $this->success([
            'month' => $monthKey,
            'snapshot' => $snapshot,
            'cycle' => $cycle ? $this->payoutService->serializeCycle($cycle) : null,
        ]);
    }

    public function cycles(Request $request)
    {
        $cycles = PayoutCycle::query()
            ->with(['coachPayouts.coach.user'])
            ->when($request->filled('month'), function ($query) use ($request) {
                $query->where('month_key', $request->get('month'));
            })
            ->latest('month_key')
            ->paginate((int) $request->get('per_page', 12));

        $cycles->setCollection(
            $cycles->getCollection()->map(fn (PayoutCycle $cycle) => $this->payoutService->serializeCycle($cycle))
        );

        return $this->success($cycles);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        try {
            $month = $this->payoutService->normalizeMonth($request->get('month'));
            $cycle = $this->payoutService->generateCycle($month, (int) $request->user()->id);

            return $this->success(
                $this->payoutService->serializeCycle($cycle),
                'Payout cycle generated successfully.'
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function approve(Request $request, int $id)
    {
        $cycle = PayoutCycle::query()->findOrFail($id);

        try {
            $approved = $this->payoutService->approveCycle($cycle);
            foreach ($approved->coachPayouts as $payout) {
                $this->notificationService->payoutStatus($payout, 'approved');
            }

            return $this->success(
                $this->payoutService->serializeCycle($approved),
                'Payout cycle approved successfully.'
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function markPaid(Request $request, int $id)
    {
        $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $cycle = PayoutCycle::query()->findOrFail($id);

        try {
            $paid = $this->payoutService->markCyclePaid($cycle, $request->string('reference')->toString() ?: null);
            foreach ($paid->coachPayouts as $payout) {
                $this->notificationService->payoutStatus($payout, 'paid');
            }

            return $this->success(
                $this->payoutService->serializeCycle($paid),
                'Payout cycle marked as paid.'
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
