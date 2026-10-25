<?php

namespace App\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Services\Concerns\FailsValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * requested -> approved -> received -> completed   (or rejected)
 *
 * "received" is the moment the warehouse has the goods and has inspected them: OrderService publishes
 * return.received with each line's condition, and Inventory puts resellable goods back into stock.
 * "completed" creates the refund.
 */
class ReturnService
{
    use FailsValidation;

    public function __construct(
        private OrderNumberService $numbers,
        private OrderEventService $events,
        private OrderEventPublisher $publisher,
        private OrderService $orders,
        private RefundService $refunds,
    ) {}

    public function request(Order $order, int $customerId, array $items, string $idempotencyKey, ?string $reason = null, ?string $note = null): ReturnRequest
    {
        $key = $customerId.':'.$idempotencyKey;

        if ($existing = ReturnRequest::where('idempotency_key', $key)->first()) {
            return $existing->load('items');
        }

        return DB::transaction(function () use ($order, $customerId, $items, $key, $reason, $note) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->customer_id !== $customerId) {
                abort(404, 'Order not found.');
            }

            if (! in_array($order->status, [Order::DELIVERED, Order::COMPLETED], true)) {
                $this->fail('order', 'Only delivered orders can be returned.');
            }

            $days = (int) config('orders.return_window_days', 7);

            if ($order->delivered_at && $order->delivered_at->copy()->addDays($days)->isPast()) {
                $this->fail('order', "The {$days}-day return window has closed.");
            }

            $wanted = [];

            foreach ($items as $item) {
                $id = (int) $item['order_item_id'];
                $wanted[$id] = ($wanted[$id] ?? 0) + (int) $item['quantity'];
            }

            $return = $order->returns()->create([
                'reference' => $this->numbers->generateReturnReference(),
                'type' => ReturnRequest::TYPE_CUSTOMER,
                'status' => ReturnRequest::REQUESTED,
                'reason' => $reason,
                'customer_note' => $note,
                'idempotency_key' => $key,
                'requested_at' => now(),
            ]);

            $payload = [];

            foreach ($wanted as $orderItemId => $qty) {
                $orderItem = $order->items()->find($orderItemId)
                    ?? $this->fail('items', "Order line {$orderItemId} does not belong to this order.");

                $alreadyReturned = (int) DB::table('return_items')
                    ->join('return_requests', 'return_requests.id', '=', 'return_items.return_request_id')
                    ->where('return_items.order_item_id', $orderItemId)
                    ->whereNotIn('return_requests.status', [ReturnRequest::REJECTED, ReturnRequest::CANCELLED])
                    ->where('return_requests.id', '!=', $return->id)
                    ->sum('return_items.quantity');

                if ($qty < 1 || $qty + $alreadyReturned > $orderItem->quantity) {
                    $this->fail('items', "You can return at most ".($orderItem->quantity - $alreadyReturned)." of {$orderItem->sku}.");
                }

                $lineReason = collect($items)->firstWhere('order_item_id', $orderItemId)['reason'] ?? $reason;

                // the customer gets the item price (incl. its tax back); shipping is not refunded
                $refundAmount = round(((float) $orderItem->total / $orderItem->quantity) * $qty, 2);

                $return->items()->create([
                    'order_item_id' => $orderItem->id,
                    'variant_id' => $orderItem->variant_id,
                    'sku' => $orderItem->sku,
                    'quantity' => $qty,
                    'reason' => $lineReason,
                    'refund_amount' => $refundAmount,
                ]);

                $payload[] = ['variant_id' => $orderItem->variant_id, 'quantity' => $qty, 'reason' => $lineReason];
            }

            $this->events->record($order, 'return.requested', "Return {$return->reference} requested.", 'customer', $customerId);
            $this->publisher->publish($order, 'return.requested', ['return_number' => $return->reference, 'reason' => $reason, 'items' => $payload]);

            return $return->load('items');
        });
    }

    /** Created automatically when a courier reports a failed delivery, so the warehouse can receive the parcel. */
    public function openForFailedDelivery(Order $order, Fulfillment $fulfillment, string $reason): ReturnRequest
    {
        $return = $order->returns()->create([
            'reference' => $this->numbers->generateReturnReference(),
            'type' => ReturnRequest::TYPE_DELIVERY_FAILED,
            'status' => ReturnRequest::APPROVED,
            'reason' => $reason,
            'idempotency_key' => "delivery_failed:{$fulfillment->id}",
            'requested_at' => now(),
            'approved_at' => now(),
        ]);

        foreach ($order->items as $item) {
            $return->items()->create([
                'order_item_id' => $item->id,
                'variant_id' => $item->variant_id,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'reason' => $reason,
                'refund_amount' => 0,
            ]);
        }

        $this->events->record($order, 'return.opened', "Return {$return->reference} opened for the failed delivery.");

        return $return->load('items');
    }

    public function approve(ReturnRequest $return, ?string $staffNote, ?int $staffId): ReturnRequest
    {
        return DB::transaction(function () use ($return, $staffNote, $staffId) {
            $return = $this->lock($return);
            $this->assertStatus($return, ReturnRequest::REQUESTED, 'approved');

            $return->update(['status' => ReturnRequest::APPROVED, 'approved_at' => now(), 'staff_note' => $staffNote]);
            $this->events->record($return->order, 'return.approved', "Return {$return->reference} approved.", 'staff', $staffId);

            return $return->refresh();
        });
    }

    public function reject(ReturnRequest $return, string $staffNote, ?int $staffId): ReturnRequest
    {
        return DB::transaction(function () use ($return, $staffNote, $staffId) {
            $return = $this->lock($return);
            $this->assertStatus($return, ReturnRequest::REQUESTED, 'rejected');

            $return->update(['status' => ReturnRequest::REJECTED, 'rejected_at' => now(), 'staff_note' => $staffNote]);
            $this->events->record($return->order, 'return.rejected', "Return {$return->reference} rejected.", 'staff', $staffId);

            return $return->refresh();
        });
    }

    /**
     * The warehouse has the parcel and inspected it.
     *
     * @param  array<int, array{return_item_id:int, condition:string}>  $conditions  resellable | damaged for EVERY line
     */
    public function receive(ReturnRequest $return, array $conditions, ?int $staffId): ReturnRequest
    {
        return DB::transaction(function () use ($return, $conditions, $staffId) {
            $return = $this->lock($return);
            $this->assertStatus($return, ReturnRequest::APPROVED, 'received');

            $byId = collect($conditions)->keyBy('return_item_id');
            $payload = [];

            foreach ($return->items as $item) {
                $condition = $byId[$item->id]['condition'] ?? null;

                if (! in_array($condition, ['resellable', 'damaged'], true)) {
                    $this->fail('items', "Give a condition (resellable or damaged) for every returned line; {$item->sku} has none.");
                }

                $item->update(['condition' => $condition]);
                $payload[] = ['variant_id' => $item->variant_id, 'quantity' => $item->quantity, 'condition' => $condition];
            }

            $return->update(['status' => ReturnRequest::RECEIVED, 'received_at' => now()]);

            $order = $return->order;
            $this->events->record($order, 'return.received', "Return {$return->reference} received in the warehouse.", 'staff', $staffId);
            $this->publisher->publish($order, 'return.received', [
                'return_number' => $return->reference,
                'reason' => $return->reason,
                'items' => $payload,
            ]);

            if ($return->type === ReturnRequest::TYPE_DELIVERY_FAILED) {
                // the parcel is back: the sale did not happen
                $order->update(['fulfillment_status' => Order::FULFILLMENT_RETURNED]);
                $this->orders->cancel($order, 'Delivery failed and the parcel was returned.', 'system');
            }

            return $return->refresh();
        });
    }

    /**
     * Pays the customer back. $amount defaults to the sum of the returned lines (0 for a failed delivery: nothing was paid).
     */
    public function complete(ReturnRequest $return, ?float $amount, ?string $method, ?int $staffId): ReturnRequest
    {
        $refund = null;

        $return = DB::transaction(function () use ($return, $amount, $method, $staffId, &$refund) {
            $return = $this->lock($return);
            $this->assertStatus($return, ReturnRequest::RECEIVED, 'completed');

            $amount ??= (float) $return->items->sum('refund_amount');
            $order = $return->order;

            if ($amount > 0 && $order->payment_status !== Order::PAYMENT_PAID && $order->payment_status !== Order::PAYMENT_PARTIALLY_REFUNDED) {
                $this->fail('order', 'There is no paid amount on this order to refund.');
            }

            if ($amount > 0) {
                $refund = $this->refunds->create(
                    $order,
                    $amount,
                    $method ?? ($order->isCod() ? 'cash' : 'original'),
                    "Return {$return->reference}",
                    null,
                    $return->id,
                    $staffId
                );
            }

            $return->update(['status' => ReturnRequest::COMPLETED, 'completed_at' => now()]);
            $this->events->record($order, 'return.completed', "Return {$return->reference} completed.", 'staff', $staffId);

            return $return->refresh();
        });

        if ($refund instanceof Refund && $refund->method === 'original') {
            try {
                $this->refunds->process($refund);
            } catch (\Throwable $e) {
                Log::warning("Refund {$refund->id} for {$return->reference} not processed: ".$e->getMessage());
            }
        }

        return $return;
    }

    private function lock(ReturnRequest $return): ReturnRequest
    {
        return ReturnRequest::whereKey($return->id)->lockForUpdate()->with(['order', 'items'])->firstOrFail();
    }

    private function assertStatus(ReturnRequest $return, string $expected, string $target): void
    {
        if ($return->status !== $expected) {
            $this->fail('return', "A return that is {$return->status} cannot become {$target} (it must be {$expected}).");
        }
    }
}
