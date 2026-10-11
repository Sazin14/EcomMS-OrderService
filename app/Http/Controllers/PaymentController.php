<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\RefundService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private RefundService $refunds) {}

    public function attempts(Payment $payment)
    {
        return response()->json($payment->load('attempts'));
    }

    public function refund(Request $request, Order $order)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', 'max:40'],
            'reason' => ['required', 'string', 'max:255'],
            'payment_id' => ['nullable', 'integer', 'exists:payments,id'],
            'return_request_id' => ['nullable', 'integer', 'exists:return_requests,id'],
        ]);

        $refund = $this->refunds->create(
            $order,
            (float) $data['amount'],
            $data['method'],
            $data['reason'],
            $data['payment_id'] ?? null,
            $data['return_request_id'] ?? null,
        );

        return response()->json($refund, 201);
    }

    public function completeRefund(Request $request, \App\Models\Refund $refund)
    {
        $data = $request->validate([
            'provider_reference' => ['nullable', 'string', 'max:150'],
        ]);

        return response()->json(
            $this->refunds->complete($refund, $data['provider_reference'] ?? null)
        );
    }
}
