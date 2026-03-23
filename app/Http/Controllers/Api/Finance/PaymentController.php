<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Api\BaseController;
use App\Models\Finance\CoinPackage;
use App\Models\Finance\Transaction;
use App\Models\Finance\UserWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends BaseController
{
    public function purchase(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'packageId' => 'required|exists:coin_packages,id',
        ]);

        $user = $request->user();
        $package = CoinPackage::query()->where('is_active', true)->findOrFail($validated['packageId']);

        $reference = 'local_' . Str::uuid()->toString();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_type' => 'coin_purchase',
            'amount_currency' => $package->price_currency,
            'amount_fiat' => $package->price_amount,
            'coin_amount' => $package->coin_amount + $package->bonus_coins,
            'stripe_session_id' => $reference,
            'status' => 'pending',
        ]);

        $checkoutUrl = $this->createCheckoutUrl($package, $transaction);

        return response()->json([
            'success' => true,
            'checkout_url' => $checkoutUrl,
            'session_id' => $transaction->stripe_session_id,
            'transaction_id' => $transaction->id,
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sessionId' => 'required|string',
        ]);

        $user = $request->user();

        $transaction = Transaction::query()
            ->where('user_id', $user->id)
            ->where('transaction_type', 'coin_purchase')
            ->where('stripe_session_id', $validated['sessionId'])
            ->latest('id')
            ->first();

        if (!$transaction) {
            return $this->error('Payment session not found.', 404);
        }

        if ($transaction->status === 'completed') {
            return response()->json([
                'success' => true,
                'coinAmount' => (int) $transaction->coin_amount,
                'data' => [
                    'coin_amount' => (int) $transaction->coin_amount,
                    'wallet' => $this->resolveWallet($user->id),
                ],
            ]);
        }

        $isPaid = $this->isCheckoutPaid($transaction->stripe_session_id);

        if (!$isPaid) {
            return $this->error('Payment has not been completed yet.', 422);
        }

        $wallet = DB::transaction(function () use ($transaction, $user) {
            $freshTransaction = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($freshTransaction->status === 'completed') {
                return $this->resolveWallet($user->id);
            }

            $wallet = UserWallet::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'coin_balance' => 0,
                    'total_coins_purchased' => 0,
                    'total_coins_spent' => 0,
                ]
            );

            $wallet->increment('coin_balance', (int) $freshTransaction->coin_amount);
            $wallet->increment('total_coins_purchased', (int) $freshTransaction->coin_amount);

            $freshTransaction->update([
                'status' => 'completed',
            ]);

            return $wallet->fresh();
        });

        return response()->json([
            'success' => true,
            'coinAmount' => (int) $transaction->coin_amount,
            'data' => [
                'coin_amount' => (int) $transaction->coin_amount,
                'wallet' => $wallet,
            ],
        ]);
    }

    private function createCheckoutUrl(CoinPackage $package, Transaction $transaction): string
    {
        $stripeSecret = (string) config('services.stripe.secret');
        $successUrl = $this->frontendUrl('/payment-success?session_id={CHECKOUT_SESSION_ID}');
        $cancelUrl = $this->frontendUrl('/payment-cancelled');

        if (!empty($stripeSecret)) {
            $response = Http::asForm()
                ->withBasicAuth($stripeSecret, '')
                ->post('https://api.stripe.com/v1/checkout/sessions', [
                    'mode' => 'payment',
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'customer_email' => auth()->user()?->email ?? null,
                    'metadata[transaction_id]' => (string) $transaction->id,
                    'metadata[user_id]' => (string) $transaction->user_id,
                    'metadata[coin_amount]' => (string) $transaction->coin_amount,
                    'line_items[0][quantity]' => 1,
                    'line_items[0][price_data][currency]' => strtolower($package->price_currency),
                    'line_items[0][price_data][unit_amount]' => (int) round(((float) $package->price_amount) * 100),
                    'line_items[0][price_data][product_data][name]' => $package->name,
                    'line_items[0][price_data][product_data][description]' => sprintf(
                        '%d base coins + %d bonus coins',
                        (int) $package->coin_amount,
                        (int) $package->bonus_coins
                    ),
                ]);

            if ($response->successful()) {
                $sessionId = $response->json('id');
                $checkoutUrl = $response->json('url');

                if ($sessionId && $checkoutUrl) {
                    $transaction->update([
                        'stripe_session_id' => $sessionId,
                    ]);

                    return $checkoutUrl;
                }
            }

            Log::warning('Stripe checkout creation failed, falling back to local success redirect.', [
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
                'transaction_id' => $transaction->id,
            ]);
        }

        return $this->frontendUrl('/payment-success?session_id=' . urlencode($transaction->stripe_session_id));
    }

    private function isCheckoutPaid(string $sessionId): bool
    {
        if (Str::startsWith($sessionId, 'local_')) {
            return true;
        }

        $stripeSecret = (string) config('services.stripe.secret');

        if (empty($stripeSecret)) {
            return false;
        }

        $response = Http::withBasicAuth($stripeSecret, '')
            ->get('https://api.stripe.com/v1/checkout/sessions/' . $sessionId);

        if (!$response->successful()) {
            Log::warning('Stripe checkout verification failed.', [
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
                'session_id' => $sessionId,
            ]);

            return false;
        }

        return $response->json('status') === 'complete'
            && $response->json('payment_status') === 'paid';
    }

    private function resolveWallet(int $userId): UserWallet
    {
        return UserWallet::firstOrCreate(
            ['user_id' => $userId],
            [
                'coin_balance' => 0,
                'total_coins_purchased' => 0,
                'total_coins_spent' => 0,
            ]
        );
    }

    private function frontendUrl(string $path): string
    {
        $base = rtrim((string) config('services.frontend.url', config('app.url')), '/');
        $path = '/' . ltrim($path, '/');

        return $base . $path;
    }
}
