<?php

namespace App\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Preorder;
use App\Models\Refund;
use App\Services\Concerns\FailsValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The order life cycle. Every change of status happens here, inside a transaction together with its timeline
 * entry and its outbox event, so the database and RabbitMQ never disagree.
 *
 *   awaiting_stock -> pending -> confirmed -> processing -> shipped -> delivered -> completed
 *                        |           |            |           \-> delivery_failed
 *                        \-----------+------------+-----------------------------> cancelled
 */
class OrderService
{
    use FailsValidation;

    public function __construct(
        private OrderEventService $events,
        private OrderEventPublisher $publisher,
        private RefundService $refunds,
    ) {}

    /** pending -> confirmed. COD: staff confirm after calling the customer. Online: called by the payment webhook. */
    public function confirm(Order $order, ?int $actorId = null): Order
    {
        return DB::transaction(function () use ($order, $actorId) {
            $order = $this->lock($order);

            if ($order->status === Order::CONFIRMED) {
                return $order;   // repeat = harmless
            }

            if ($order->status !== Order::PENDING) {
                $this->fail('order', "An order that is {$order->status} cannot be confirmed.");
            }

            if (! $order->isCod() && $order->payment_status !== Order::PAYMENT_PAID) {
                $this->fail('order', 'Online orders are confirmed automatically when the payment arrives.');
            }

            $order->update(['status' => Order::CONFIRMED, 'confirmed_at' => now(), 'hold_expires_at' => null]);

            $this->events->record($order, 'order.confirmed', 'Order confirmed.', $actorId ? 'staff' : 'system', $actorId);
            $this->publisher->publish($order, 'order.confirmed');

            return $order->refresh();
        });
    }

    /**
     * Allowed until the parcel is with the courier. Customers may only cancel while nothing was confirmed
     * (the controller checks that). Paid orders get a refund automatically.
     */
    public function cancel(Order $order, string $reason, string $actorType = 'system', ?int $actorId = null): Order
    {
        $refund = null;

        $order = DB::transaction(function () use ($order, $reason, $actorType, $actorId, &$refund) {
            $order = $this->lock($order);

            if ($order->status === Order::CANCELLED) {
                return $order;
            }

            if (! in_array($order->status, [Order::PENDING, Order::AWAITING_STOCK, Order::CONFIRMED, Order::PROCESSING, Order::DELIVERY_FAILED], true)) {
                $this->fail('order', "An order that is {$order->status} cannot be cancelled. Use a return instead.");
            }

            $order->update([
                'status' => Order::CANCELLED,
                'cancelled_at' => now(),
                'hold_expires_at' => null,
                'internal_note' => trim(($order->internal_note ? $order->internal_note."\n" : '').'Cancelled: '.$reason),
            ]);

            $order->fulfillments()->whereIn('status', [Fulfillment::PICKING, Fulfillment::PACKED])->update(['status' => Fulfillment::CANCELLED]);
            $order->preorders()->whereIn('status', [Preorder::PENDING, Preorder::AVAILABLE])->update(['status' => Preorder::CANCELLED, 'cancelled_at' => now()]);

            $this->events->record($order, 'order.cancelled', 'Order cancelled: '.$reason, $actorType, $actorId);
            $this->publisher->publish($order, 'order.cancelled', ['reason' => $reason]);

            // money already received goes back
            if ($order->payment_status === Order::PAYMENT_PAID) {
                $refund = $this->refunds->create(
                    $order,
                    $order->grand_total,
                    $order->isCod() ? 'cash' : 'original',
                    'Order cancelled: '.$reason,
                    actorId: $actorId
                );
            }

            return $order->refresh();
        });

        // gateway call after the commit; if it fails the refund simply stays "pending" for staff
        if ($refund && $refund->method === 'original') {
            try {
                $this->refunds->process($refund);
            } catch (\Throwable $e) {
                Log::warning("Automatic refund {$refund->id} for {$order->order_number} not processed: ".$e->getMessage());
            }
        }

        return $order;
    }

    /**
     * Moves an order forward for the fulfillment / return code. Call inside your own transaction.
     *
     * @param  array<int, string>  $from  statuses the order may currently have
     */
    public function transition(Order $order, array $from, string $to, array $attributes = []): Order
    {
        $order = $this->lock($order);

        if ($order->status === $to) {
            return $order;
        }

        if (! in_array($order->status, $from, true)) {
            $this->fail('order', "An order that is {$order->status} cannot move to {$to}.");
        }

        $order->update(['status' => $to] + $attributes);

        return $order;
    }

    /** delivered -> completed once the return window is over (scheduled command). */
    public function complete(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = $this->transition($order, [Order::DELIVERED], Order::COMPLETED, ['completed_at' => now()]);
            $this->events->record($order, 'order.completed', 'Return window over; order completed.');

            return $order;
        });
    }

    // ---- reactions to Inventory events (RabbitMQ queue order.inventory) -------------------------------------------

    /** The stock hold of an unconfirmed / unpaid order ran out. */
    public function handleReservationExpired(string $orderNumber): void
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (! $order || ! in_array($order->status, [Order::PENDING, Order::AWAITING_STOCK], true)) {
            return;   // unknown, already confirmed or already cancelled: nothing to do
        }

        $this->cancel($order, 'The stock hold expired before the order was confirmed or paid.');
    }

    /**
     * Inventory could not apply an event we sent (order.confirmed / shipped / cancelled / return.received).
     * Nobody waits for an answer, so we react here.
     */
    public function handleInventoryRejection(string $orderNumber, string $event, string $reason): void
    {
        $order = Order::where('order_number', $orderNumber)->first();

        if (! $order) {
            return;
        }

        $this->events->record($order, 'inventory.rejected', "Inventory refused {$event}: {$reason}", 'system', null, ['event' => $event]);

        if ($event === 'order.confirmed' && in_array($order->status, [Order::CONFIRMED, Order::PROCESSING], true)) {
            // the stock is gone (hold expired between phone call and confirm): the order cannot be honoured
            $this->cancel($order, "Stock is no longer available ({$reason}).");

            return;
        }

        if ($event === 'order.cancelled') {
            return;   // nothing held anymore: fine
        }

        // shipped / return.received refused: a person must look
        $order->update([
            'needs_attention' => true,
            'attention_note' => trim(($order->attention_note ? $order->attention_note."\n" : '')."Inventory refused {$event}: {$reason}"),
        ]);
    }

    private function lock(Order $order): Order
    {
        return Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
    }
}
