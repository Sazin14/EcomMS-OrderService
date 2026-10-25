<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use Illuminate\Support\Str;

/**
 * A fake gateway for development: no money moves. "Paying" = a signed webhook call, which
 * `php artisan payments:simulate <provider_reference>` sends for you.
 */
class SandboxGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'sandbox';
    }

    public function initiate(Payment $payment, PaymentAttempt $attempt): array
    {
        $reference = 'SBX-'.strtoupper(Str::random(14));

        return [
            'provider_reference' => $reference,
            'payment_url' => rtrim((string) config('orders.gateway.payment_url'), '/').'?ref='.$reference,
        ];
    }

    public function parseWebhook(string $rawBody, ?string $signature): array
    {
        if (! $signature || ! hash_equals($this->sign($rawBody), $signature)) {
            throw new \InvalidArgumentException('Bad webhook signature.');
        }

        $data = json_decode($rawBody, true);

        if (! is_array($data) || empty($data['provider_reference']) || ! in_array($data['status'] ?? null, ['succeeded', 'failed'], true)) {
            throw new \InvalidArgumentException('Malformed webhook body.');
        }

        return [
            'provider_reference' => (string) $data['provider_reference'],
            'status' => $data['status'],
            'amount' => isset($data['amount']) ? (string) $data['amount'] : null,
            'failure_code' => $data['failure_code'] ?? null,
            'failure_message' => $data['failure_message'] ?? null,
        ];
    }

    public function refund(Refund $refund, Payment $payment): array
    {
        return ['status' => 'succeeded', 'provider_reference' => 'SBX-RF-'.strtoupper(Str::random(12))];
    }

    public function sign(string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, (string) config('orders.gateway.webhook_secret'));
    }
}
