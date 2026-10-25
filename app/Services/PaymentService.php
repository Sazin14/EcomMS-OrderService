<?php

namespace App\Services;

use App\Exceptions\ServiceUnavailableException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\Refund;
use App\Services\Concerns\FailsValidation;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Online payment: customer starts it (startOnline) -> gateway calls our webhook (handleWebhook) -> order confirmed.
 * COD: the courier collects cash at the door; staff mark it (markCodCollected) when the parcel is delivered.
 */
class PaymentService
{
    use FailsValidation;

    public function __construct(
        private PaymentGateway $gateway,
        private OrderService $orders,
        private RefundService $refunds,
        private OrderEventService $events,
        private OrderEventPublisher $publisher,
    ) {}

    /** @return array{payment_id:int, attempt_id:int, provider_reference:string, payment_url:string, amount:string, currency:string} */
    public function startOnline(Order $order): array
    {
        $attempt = DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isCod()) {
                $this->fail('order', 'This is a cash-on-delivery order; there is nothing to pay online.');
            }

            if ($order->status !== Order::PENDING) {
                $this->fail('order', $order->status === Order::AWAITING_STOCK
                    ? 'You can pay once the pre-ordered stock has arrived.'
                    : "An order that is {$order->status} cannot be paid.");
            }

            if ($order->payment_status === Order::PAYMENT_PAID) {
                $this->fail('order', 'This order is already paid.');
            }

            if ($order->hold_expires_at && $order->hold_expires_at->isPast()) {
                $this->fail('order', 'The payment time for this order has run out.');
            }

            $payment = Payment::whereKey($order->payments()->latest('id')->value('id'))->lockForUpdate()->firstOrFail();

            // an earlier attempt that never finished is abandoned; only the newest one counts
            $payment->attempts()->where('status', 'initiated')->update(['status' => 'abandoned']);

            $payment->update(['status' => Payment::PROCESSING]);
            $order->update(['payment_status' => Order::PAYMENT_PENDING]);

            return $payment->attempts()->create([
                'provider' => $this->gateway->name(),
                'status' => 'initiated',
                'amount' => $payment->amount,
                'request_id' => (string) Str::uuid(),
            ]);
        });

        $payment = $attempt->payment;

        try {
            $started = $this->gateway->initiate($payment, $attempt);
        } catch (\Throwable $e) {
            $attempt->update(['status' => 'failed', 'failure_code' => 'gateway_error', 'failure_message' => $e->getMessage(), 'processed_at' => now()]);

            throw new ServiceUnavailableException('The payment provider could not be reached.');
        }

        $attempt->update(['provider_reference' => $started['provider_reference']]);

        return [
            'payment_id' => $payment->id,
            'attempt_id' => $attempt->id,
            'provider_reference' => $started['provider_reference'],
            'payment_url' => $started['payment_url'],
            'amount' => (string) $payment->amount,
            'currency' => $payment->currency,
        ];
    }

    /**
     * The gateway's server calls us (no JWT: the signature proves it). Always safe to repeat.
     *
     * @throws \InvalidArgumentException  bad signature (the controller answers 401)
     */
    public function handleWebhook(string $rawBody, ?string $signature): string
    {
        $event = $this->gateway->parseWebhook($rawBody, $signature);

        $refundToProcess = null;

        $result = DB::transaction(function () use ($event, &$refundToProcess) {
            $attempt = PaymentAttempt::where('provider_reference', $event['provider_reference'])->lockForUpdate()->first();

            if (! $attempt) {
                return 'unknown_reference';
            }

            if ($attempt->status !== 'initiated') {
                return 'duplicate';   // already handled (or abandoned)
            }

            $payment = Payment::whereKey($attempt->payment_id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            if ($event['status'] === 'failed') {
                $attempt->update([
                    'status' => 'failed',
                    'failure_code' => $event['failure_code'],
                    'failure_message' => $event['failure_message'],
                    'processed_at' => now(),
                ]);
                $payment->update(['status' => Payment::FAILED]);
                $order->update(['payment_status' => Order::PAYMENT_FAILED]);

                $this->events->record($order, 'payment.failed', 'Online payment failed.', 'system', null, ['reason' => $event['failure_message']]);
                $this->publisher->publish($order, 'payment.failed', ['reason' => $event['failure_message'] ?? 'Payment failed.']);

                return 'failed';
            }

            // succeeded: the amount must match exactly, otherwise a human checks it
            if ($event['amount'] !== null && abs((float) $event['amount'] - (float) $payment->amount) > 0.001) {
                $attempt->update(['status' => 'failed', 'failure_code' => 'amount_mismatch', 'failure_message' => "Gateway reported {$event['amount']}, expected {$payment->amount}.", 'processed_at' => now()]);
                $order->update(['needs_attention' => true, 'attention_note' => trim(($order->attention_note ? $order->attention_note."\n" : '')."Payment amount mismatch: gateway {$event['amount']}, expected {$payment->amount}.")]);

                return 'amount_mismatch';
            }

            $attempt->update(['status' => 'succeeded', 'processed_at' => now()]);
            $payment->update(['status' => Payment::PAID, 'paid_at' => now(), 'transaction_reference' => $event['provider_reference']]);
            $order->update(['payment_status' => Order::PAYMENT_PAID]);

            $this->events->record($order, 'payment.paid', 'Online payment received.', 'system', null, ['reference' => $event['provider_reference']]);
            $this->publisher->publish($order, 'payment.paid', ['reference' => $event['provider_reference']]);

            if ($order->status === Order::PENDING) {
                $this->orders->confirm($order);   // paid -> confirmed -> Inventory is told to keep the stock
            } elseif ($order->status === Order::CANCELLED) {
                // the customer paid after the order was cancelled / expired: give the money back
                $refundToProcess = $this->refunds->create($order, $payment->amount, 'original', 'Payment received after the order was cancelled.');
            }

            return 'paid';
        });

        if ($refundToProcess instanceof Refund) {
            try {
                $this->refunds->process($refundToProcess);
            } catch (\Throwable $e) {
                Log::warning("Late-payment refund {$refundToProcess->id} not processed: ".$e->getMessage());
            }
        }

        return $result;
    }

    /** Cash handed over at the door. Called when staff mark a COD parcel delivered. */
    public function markCodCollected(Order $order, ?int $actorId = null): void
    {
        DB::transaction(function () use ($order, $actorId) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->payment_status === Order::PAYMENT_PAID) {
                return;
            }

            Payment::whereKey($order->payments()->latest('id')->value('id'))->update(['status' => Payment::PAID, 'paid_at' => now()]);
            $order->update(['payment_status' => Order::PAYMENT_PAID]);

            $this->events->record($order, 'payment.cod_collected', 'Cash collected on delivery.', $actorId ? 'staff' : 'system', $actorId);
            $this->publisher->publish($order, 'payment.paid', ['reference' => 'cod']);
        });
    }
}
