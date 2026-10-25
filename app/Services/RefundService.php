<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Concerns\FailsValidation;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;

/**
 * pending -> (gateway) processing -> completed | failed
 * "original" refunds go back through the payment gateway; "cash" / "bank_transfer" / "mobile_wallet"
 * are paid by a person and marked completed by staff.
 */
class RefundService
{
    use FailsValidation;

    public const METHODS = ['original', 'cash', 'bank_transfer', 'mobile_wallet'];

    public function __construct(
        private OrderEventService $events,
        private OrderEventPublisher $publisher,
        private PaymentGateway $gateway,
    ) {}

    public function create(
        Order $order,
        float|string $amount,
        string $method,
        string $reason,
        ?int $paymentId = null,
        ?int $returnRequestId = null,
        ?int $actorId = null
    ): Refund {
        $amount = round((float) $amount, 2);

        if ($amount <= 0) {
            $this->fail('amount', 'Refund amount must be greater than zero.');
        }

        if (! in_array($method, self::METHODS, true)) {
            $this->fail('method', 'Refund method must be one of: '.implode(', ', self::METHODS).'.');
        }

        return DB::transaction(function () use ($order, $amount, $method, $reason, $paymentId, $returnRequestId, $actorId) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($order->payment_status, [Order::PAYMENT_PAID, Order::PAYMENT_PARTIALLY_REFUNDED], true)) {
                $this->fail('order', 'This order has no paid amount to refund.');
            }

            $alreadyPromised = (float) $order->refunds()->whereIn('status', [Refund::PENDING, Refund::PROCESSING, Refund::COMPLETED])->sum('amount');
            $left = round((float) $order->grand_total - $alreadyPromised, 2);

            if ($amount > $left + 0.0001) {
                $this->fail('amount', "At most {$left} can still be refunded for this order.");
            }

            $paymentId ??= $order->payments()->where('status', '!=', Payment::FAILED)->latest('id')->value('id');

            $refund = $order->refunds()->create([
                'payment_id' => $paymentId,
                'return_request_id' => $returnRequestId,
                'amount' => $amount,
                'currency' => $order->currency,
                'method' => $method,
                'status' => Refund::PENDING,
                'reason' => $reason,
            ]);

            $this->events->record($order, 'refund.created', "Refund of {$amount} created ({$method}).", $actorId ? 'staff' : 'system', $actorId, ['refund_id' => $refund->id]);

            return $refund;
        });
    }

    /** Sends an "original" refund to the gateway and completes it when the gateway says yes. */
    public function process(Refund $refund): Refund
    {
        if ($refund->method !== 'original') {
            $this->fail('refund', 'Only "original" refunds go through the gateway. Mark this one completed by hand.');
        }

        if ($refund->status !== Refund::PENDING) {
            return $refund;   // already processing / completed / failed: calling twice is harmless
        }

        $refund->update(['status' => Refund::PROCESSING]);

        $result = $this->gateway->refund($refund, $refund->payment ?? $refund->order->payments()->latest('id')->firstOrFail());

        if ($result['status'] !== 'succeeded') {
            $refund->update(['status' => Refund::FAILED]);
            $this->events->record($refund->order, 'refund.failed', 'The gateway refused the refund.', null, null, ['refund_id' => $refund->id]);

            return $refund->refresh();
        }

        return $this->complete($refund, $result['provider_reference'] ?? null);
    }

    public function complete(Refund $refund, ?string $providerReference = null, ?int $actorId = null): Refund
    {
        return DB::transaction(function () use ($refund, $providerReference, $actorId) {
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($refund->status === Refund::COMPLETED) {
                return $refund;
            }

            if ($refund->status === Refund::FAILED) {
                $this->fail('refund', 'A failed refund cannot be completed. Create a new one.');
            }

            $refund->update([
                'status' => Refund::COMPLETED,
                'provider_reference' => $providerReference,
                'processed_at' => now(),
            ]);

            $order = Order::whereKey($refund->order_id)->lockForUpdate()->firstOrFail();

            $refunded = (float) $order->refunds()->where('status', Refund::COMPLETED)->sum('amount');
            $full = $refunded + 0.0001 >= (float) $order->grand_total;

            $order->update(['payment_status' => $full ? Order::PAYMENT_REFUNDED : Order::PAYMENT_PARTIALLY_REFUNDED]);

            if ($refund->payment_id) {
                Payment::whereKey($refund->payment_id)->update(['status' => $full ? Payment::REFUNDED : Payment::PARTIALLY_REFUNDED]);
            }

            $this->events->record($order, 'refund.completed', "Refund of {$refund->amount} completed.", $actorId ? 'staff' : 'system', $actorId, ['refund_id' => $refund->id]);

            $this->publisher->publish($order, 'refund.processed', [
                'amount' => (string) $refund->amount,
                'method' => $refund->method,
                'reference' => $providerReference ?? "refund-{$refund->id}",
            ]);

            return $refund->refresh();
        });
    }
}
