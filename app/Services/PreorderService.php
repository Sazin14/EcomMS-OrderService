<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Preorder;
use Illuminate\Support\Facades\DB;

class PreorderService
{
    public function __construct(
        private OrderEventService $events,
        private OrderEventPublisher $publisher,
    ) {}

    /** One row per pre-ordered line. Called by checkout inside its transaction. */
    public function createForOrder(Order $order, array $lines): void
    {
        foreach ($lines as $line) {
            Preorder::create([
                'reference' => "PRE-{$order->order_number}-{$line['variant_id']}",
                'customer_id' => $order->customer_id,
                'variant_id' => $line['variant_id'],
                'sku' => $line['sku'],
                'quantity' => $line['quantity'],
                'status' => Preorder::PENDING,
                'order_id' => $order->id,
            ]);
        }
    }

    /**
     * Event inventory.preorder.allocated: stock arrived and is held for this line (reference = order number).
     * When every line of the order has its stock, the order leaves "awaiting_stock" and the customer is told.
     * Safe to repeat.
     */
    public function allocate(string $orderNumber, int $variantId, int $reservationId): void
    {
        DB::transaction(function () use ($orderNumber, $variantId, $reservationId) {
            $order = Order::where('order_number', $orderNumber)->lockForUpdate()->first();

            if (! $order) {
                return;
            }

            $line = $order->preorders()->where('variant_id', $variantId)->lockForUpdate()->first();

            if (! $line || $line->status !== Preorder::PENDING) {
                return;   // unknown, repeated, or the line was cancelled meanwhile
            }

            $line->update(['status' => Preorder::AVAILABLE, 'reservation_id' => $reservationId, 'allocated_at' => now()]);

            $this->events->record($order, 'preorder.allocated', "Stock is held for {$line->sku}.", 'system', null, ['variant_id' => $variantId]);

            $allReady = $order->preorders()->whereNotIn('status', [Preorder::AVAILABLE, Preorder::CONVERTED])->doesntExist();

            if ($allReady && $order->status === Order::AWAITING_STOCK) {
                $order->update([
                    'status' => Order::PENDING,
                    'hold_expires_at' => now()->addHours((int) config('orders.preorder_confirm_window_hours', 48)),
                ]);

                $this->events->record($order, 'order.stock_ready', 'All pre-ordered items are in stock.');
                $this->publisher->publish($order, 'order.preorder_ready', [
                    'pay_before' => $order->hold_expires_at->toIso8601ZuluString(),
                ]);
            }
        });
    }
}
