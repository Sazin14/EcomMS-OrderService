<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Services\Clients\InventoryServiceClient;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CheckoutService
{
    public function __construct(
        private PricingService $pricing,
        private OrderNumberService $numbers,
        private OrderEventService $events,
        private InventoryServiceClient $inventory,
    ) {}

    public function createOrder(
        Cart $cart,
        array $shippingAddress,
        ?array $billingAddress,
        string $idempotencyKey,
        string $paymentMethod = 'cod',
        float $discount = 0,
        float $tax = 0,
        float $shipping = 0,
        ?string $customerNote = null
    ): Order {
        if ($cart->items->isEmpty()) {
            throw new RuntimeException('Cart is empty.');
        }

        $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $existing->load('items', 'events');
        }

        $items = $cart->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'sku' => $item->sku,
            'product_name' => $item->product_name,
            'variant_name' => $item->variant_name,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
        ])->values()->all();

        $totals = $this->pricing->calculate($items, $discount, $tax, $shipping);

        $reservation = $this->inventory->reserve([
            'reference' => $idempotencyKey,
            'idempotency_key' => $idempotencyKey,
            'items' => collect($items)->map(fn ($item) => [
                'variant_id' => $item['variant_id'],
                'sku' => $item['sku'],
                'quantity' => $item['quantity'],
                'warehouse_id' => null,
            ])->all(),
        ]);

        return DB::transaction(function () use (
            $cart,
            $shippingAddress,
            $billingAddress,
            $idempotencyKey,
            $paymentMethod,
            $items,
            $totals,
            $customerNote,
            $reservation
        ) {
            $order = Order::create([
                'order_number' => $this->numbers->generate(),
                'customer_id' => $cart->customer_id,
                'status' => Order::PENDING,
                'payment_status' => Order::PAYMENT_PENDING,
                'fulfillment_status' => Order::FULFILLMENT_UNFULFILLED,
                'currency' => 'BDT',
                ...$totals,
                'billing_address' => $billingAddress ?? $shippingAddress,
                'shipping_address' => $shippingAddress,
                'customer_note' => $customerNote,
                'idempotency_key' => $idempotencyKey,
                'placed_at' => now(),
            ]);

            foreach ($items as $item) {
                $subtotal = $item['unit_price'] * $item['quantity'];

                $order->items()->create([
                    ...$item,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                ]);
            }

            $order->payments()->create([
                'method' => $paymentMethod,
                'status' => 'pending',
                'amount' => $totals['grand_total'],
                'currency' => 'BDT',
            ]);

            $this->events->record(
                $order,
                'order.created',
                'Order created and inventory reservation requested.',
                null,
                null,
                ['reservation' => $reservation]
            );

            $cart->update(['status' => 'converted']);

            return $order->load('items', 'payments', 'events');
        });
    }
}
