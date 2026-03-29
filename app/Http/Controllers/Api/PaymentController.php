<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function createPayment(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($order->status !== 'pending' || $order->payment->status === 'paid') {
            return response()->json(['message' => 'Order is not pending or already paid'], 400);
        }

        // Logic to generate payment gateway URL (e.g. VNPAY, Momo) goes here.
        // For demonstration, we simply return a mock URL.
        
        $paymentUrl = 'https://mock-payment-gateway.com/pay/' . $order->id;

        return response()->json(['payment_url' => $paymentUrl]);
    }

    public function handleCallback(Request $request)
    {
        // Typically, you would validate the signature of the payment gateway here.
        
        $orderId = $request->input('order_id');
        $status = $request->input('status'); // e.g., 'success' or 'failed'

        $order = Order::with('payment')->findOrFail($orderId);

        if ($status === 'success') {
            DB::transaction(function () use ($order) {
                $order->update(['status' => 'processing']);
                $order->payment()->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            });

            return response()->json(['message' => 'Payment successful']);
        }

        $order->payment()->update(['status' => 'failed']);
        return response()->json(['message' => 'Payment failed'], 400);
    }
}
