<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Initiate payment transaction
     */
    public function initiate(Request $request)
    {
        $request->validate([
            'tipster_id' => 'required|exists:users,id',
            'plan' => 'required|in:daily,weekly,monthly',
        ]);

        $price = match ($request->plan) {
            'daily' => 500,
            'weekly' => 2000,
            'monthly' => 5000,
        };

        $payment = Payment::create([
            'user_id' => auth()->id(),
            'tipster_id' => $request->tipster_id,
            'plan' => $request->plan,
            'amount' => $price,
            'status' => 'pending',
        ]);

        // TODO: Integrate Selcom API here to send request and return redirect/transaction URL
        return response()->json(['payment_id' => $payment->id]);
    }

    /**
     * Handle payment webhook from Selcom
     */
    public function webhook(Request $request)
    {
        // Validate request from Selcom, update payment status
        $payment = Payment::where('selcom_transaction_id', $request->txnid)->first();
        if ($payment) {
            $payment->status = 'success';
            $payment->save();
        }
        return response()->json(['status' => 'received']);
    }
}
