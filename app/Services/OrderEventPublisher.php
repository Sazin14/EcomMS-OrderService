<?php

namespace App\Services;

use App\Models\Order;
use App\Support\Outbox;

/**
 * Puts an order event into the outbox (see EVENTS-order.md for the contract).
 * ALWAYS call it inside the same DB::transaction that changed the order: the event is saved with the change
 * and the outbox worker (php artisan outbox:publish) sends it to RabbitMQ afterwards.
 */
class OrderEventPublisher
{
    public function publish(Order $order, string $type, array $extra = []): void
    {
        Outbox::record($type, $this->orderBlock($order) + $extra);
    }

    /** What listeners need to know about an order (never card data). */
    public function orderBlock(Order $order): array
    {
        $order->loadMissing('items');
        $shipping = (array) $order->shipping_address;

        return [
            'order_number' => $order->order_number,
            'type' => $order->type,
            'status' => $order->status,
            'placed_at' => $order->placed_at?->toIso8601ZuluString(),
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'currency' => $order->currency,
            'subtotal' => (string) $order->subtotal,
            'shipping_fee' => (string) $order->shipping_total,
            'discount_total' => (string) $order->discount_total,
            'total' => (string) $order->grand_total,
            'customer' => [
                'id' => $order->customer_id,
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
                'locale' => 'en',
            ],
            'shipping' => [
                'name' => $shipping['name'] ?? $order->customer_name,
                'phone' => $shipping['phone'] ?? $order->customer_phone,
                'address' => $shipping['address'] ?? null,
                'city' => $shipping['city'] ?? null,
                'postcode' => $shipping['postcode'] ?? null,
                'country' => $shipping['country'] ?? 'BD',
            ],
            'items' => $order->items->map(fn ($i) => [
                'variant_id' => $i->variant_id,
                'sku' => $i->sku,
                'name' => $i->product_name,
                'variant_label' => $i->variant_name,
                'quantity' => $i->quantity,
                'unit_price' => (string) $i->unit_price,
                'line_total' => (string) $i->total,
            ])->values()->all(),
        ];
    }
}
