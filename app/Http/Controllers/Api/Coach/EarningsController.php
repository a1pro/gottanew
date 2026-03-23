<?php

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Api\BaseController;
use App\Services\Finance\PayoutService;
use Illuminate\Http\Request;

class EarningsController extends BaseController
{
    public function __construct(private readonly PayoutService $payoutService)
    {
    }

    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        try {
            $data = $this->payoutService->coachEarningsData((int) $request->user()->id, $request->get('month'));

            return $this->success($data);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 404);
        }
    }
}
