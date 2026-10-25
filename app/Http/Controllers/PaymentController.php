<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    /**
     * POST /api/payments/webhook : called by the payment gateway, NOT by a browser. No JWT; the signature header proves it.
     * Always answers 200 for a valid call (even a repeat), so the gateway stops retrying.
     */
    public function webhook(Request $request)
    {
        try {
            $result = $this->payments->handleWebhook($request->getContent(), $request->header('X-Signature'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        return response()->json(['result' => $result]);
    }

    /** Staff: every try the customer made to pay. */
    public function attempts(Payment $payment)
    {
        return $payment->load('attempts');
    }
}
