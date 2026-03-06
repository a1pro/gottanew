<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Finance\Transaction;

class TransactionController extends Controller
{
    public function index()
    {
        return Transaction::latest()->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'transaction_type' => 'required|in:coin_purchase,coach_payment,refund',
            'amount_currency' => 'nullable|string',
            'amount_fiat' => 'nullable|numeric',
            'coin_amount' => 'required|integer',
            'coach_id' => 'nullable|exists:coaches,id',
            'package_id' => 'nullable|exists:coaching_packages,id',
            'stripe_session_id' => 'nullable|string',
            'status' => 'required|in:pending,completed,failed,refunded'
        ]);

        $data['user_id'] = auth()->id();

        $transaction = Transaction::create($data);

        return response()->json([
            'message' => 'Transaction created successfully',
            'data' => $transaction
        ], 201);
    }

    public function show($id)
    {
        return Transaction::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);

        $transaction->update($request->all());

        return response()->json($transaction);
    }

    public function destroy($id)
    {
        $transaction = Transaction::findOrFail($id);
        $transaction->delete();

        return response()->json([
            'message' => 'Transaction deleted'
        ]);
    }
}