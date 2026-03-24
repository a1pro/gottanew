<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\Finance\PayoutCycle;
use App\Models\Finance\TokenGrant;
use App\Models\Finance\UserWallet;
use App\Models\User;
use App\Services\Finance\PayoutService;
use App\Services\Communication\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayoutController extends BaseController
{
    public function __construct(
        private readonly PayoutService $payoutService,
        private readonly NotificationService $notificationService
    ) {
    }


    public function clientWallets(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $clients = User::query()
            ->with('wallet')
            ->whereHas('roles', function ($query) {
                $query->where('role', 'client');
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($builder) use ($q) {
                    $builder->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->latest('id')
            ->paginate((int) $request->get('per_page', 8));

        $clients->setCollection($clients->getCollection()->map(function (User $user) {
            return [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'wallet' => [
                    'coin_balance' => (int) ($user->wallet?->coin_balance ?? 0),
                    'total_coins_purchased' => (int) ($user->wallet?->total_coins_purchased ?? 0),
                    'total_coins_spent' => (int) ($user->wallet?->total_coins_spent ?? 0),
                ],
            ];
        }));

        return $this->success($clients);
    }

    public function tokenGrants(Request $request)
    {
        $grants = TokenGrant::query()
            ->with(['user:id,name,email', 'grantedBy:id,name,email'])
            ->when($request->filled('user_id'), function ($query) use ($request) {
                $query->where('user_id', (int) $request->get('user_id'));
            })
            ->latest('id')
            ->paginate((int) $request->get('per_page', 10));

        $grants->setCollection(
            $grants->getCollection()->map(fn (TokenGrant $grant) => $this->serializeGrant($grant))
        );

        return $this->success($grants);
    }

    public function grantTokens(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'coin_amount' => ['required', 'integer', 'min:1', 'max:500'],
            'reason' => ['required', 'string', 'in:onboarding,promo,manual_adjustment'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $client = User::query()
            ->with(['roles', 'wallet'])
            ->findOrFail((int) $validated['user_id']);

        if (!$client->hasRole('client')) {
            return $this->error('Tokens can only be granted to client accounts.', 422);
        }

        $result = DB::transaction(function () use ($validated, $client, $request) {
            $wallet = UserWallet::firstOrCreate(
                ['user_id' => $client->id],
                [
                    'coin_balance' => 0,
                    'total_coins_purchased' => 0,
                    'total_coins_spent' => 0,
                ]
            );

            $wallet->increment('coin_balance', (int) $validated['coin_amount']);

            $grant = TokenGrant::query()->create([
                'user_id' => $client->id,
                'granted_by_user_id' => (int) $request->user()->id,
                'coin_amount' => (int) $validated['coin_amount'],
                'reason' => $validated['reason'],
                'note' => $validated['note'] ?? null,
            ]);

            return [
                'wallet' => $wallet->fresh(),
                'grant' => $grant->load(['user:id,name,email', 'grantedBy:id,name,email']),
            ];
        });

        $reasonLabel = match ($validated['reason']) {
            'onboarding' => 'onboarding credit',
            'promo' => 'promotional credit',
            default => 'manual token credit',
        };

        $this->notificationService->createForUser($client, [
            'category' => 'token_grant',
            'priority' => 'normal',
            'title' => 'Tokens added to your wallet',
            'body' => sprintf(
                '%d token%s were added to your wallet as %s.',
                (int) $validated['coin_amount'],
                (int) $validated['coin_amount'] === 1 ? '' : 's',
                $reasonLabel
            ),
            'action_url' => '/profile',
            'metadata' => [
                'coin_amount' => (int) $validated['coin_amount'],
                'reason' => $validated['reason'],
            ],
        ]);

        return $this->success([
            'wallet' => $result['wallet'],
            'grant' => $this->serializeGrant($result['grant']),
        ], 'Tokens granted successfully.');
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

    private function serializeGrant(TokenGrant $grant): array
    {
        return [
            'id' => (int) $grant->id,
            'coin_amount' => (int) $grant->coin_amount,
            'reason' => $grant->reason,
            'note' => $grant->note,
            'created_at' => optional($grant->created_at)?->toISOString(),
            'user' => $grant->user ? [
                'id' => (int) $grant->user->id,
                'name' => $grant->user->name,
                'email' => $grant->user->email,
            ] : null,
            'granted_by' => $grant->grantedBy ? [
                'id' => (int) $grant->grantedBy->id,
                'name' => $grant->grantedBy->name,
                'email' => $grant->grantedBy->email,
            ] : null,
        ];
    }
}
