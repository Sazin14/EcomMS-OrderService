<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;

/** What the rest of the code needs from a payment provider. Add one class per real provider (bKash, SSLCommerz, Stripe ...). */
interface PaymentGateway
{
    public function name(): string;

    /** Start a payment. @return array{provider_reference:string, payment_url:string} */
    public function initiate(Payment $payment, PaymentAttempt $attempt): array;

    /**
     * Check the signature of a webhook call and turn it into a neutral array.
     *
     * @return array{provider_reference:string, status:string, amount:?string, failure_code:?string, failure_message:?string}
     *         status is "succeeded" or "failed"
     *
     * @throws \InvalidArgumentException when the signature or body is wrong
     */
    public function parseWebhook(string $rawBody, ?string $signature): array;

    /** Pay money back. @return array{status:string, provider_reference:?string}  status "succeeded" | "failed" */
    public function refund(Refund $refund, Payment $payment): array;
}
