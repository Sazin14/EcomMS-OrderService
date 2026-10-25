<?php

namespace App\Services;

use App\Exceptions\CheckoutException;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Services\Clients\InventoryServiceClient;
use App\Services\Clients\ProductServiceClient;
use App\Services\Concerns\FailsValidation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * cart -> order. Prices come from ProductService, stock is held by Inventory, then ONE database
 * transaction writes the order, its lines, the payment row, the pre-order rows and the "order.placed" outbox event.
 */
class CheckoutService
{
    use FailsValidation;

    public function __construct(
        private CartService $carts,
        private ProductServiceClient $products,
        private InventoryServiceClient $inventory,
        private PricingService $pricing,
        private OrderNumberService $numbers,
        private PreorderService $preorders,
        private OrderEventService $events,
        private OrderEventPublisher $publisher,
    ) {}

    /**
     * @param  array{idempotency_key:string, payment_method:string, shipping_address:array, billing_address:?array, contact:array{name:string, phone:string, email:?string}, customer_note:?string, accept_preorder:bool}  $in
     * @return array{0: Order, 1: bool}  [order, created]  (created = false when this is a repeat of an earlier request)
     */
    public function place(int $customerId, array $in): array
    {
        $key = $customerId.':'.$in['idempotency_key'];

        if ($existing = Order::where('idempotency_key', $key)->first()) {
            return [$existing->load('items', 'payments', 'preorders'), false];
        }

        $cart = $this->carts->getOrCreate($customerId)->load('items');

        if ($cart->items->isEmpty()) {
            $this->fail('cart', 'Your cart is empty.');
        }

        // 1. prices and names from ProductService, never from the browser
        $current = $this->products->variants($cart->items->pluck('variant_id')->all());
        $lines = [];
        $unavailable = [];

        foreach ($cart->items as $item) {
            $row = $current[$item->variant_id] ?? null;

            if (! $row || ! $row['is_active']) {
                $unavailable[] = ['variant_id' => $item->variant_id, 'sku' => $item->sku, 'reason' => 'no longer available'];

                continue;
            }

            $lines[] = [
                'product_id' => $row['product_id'],
                'variant_id' => $row['variant_id'],
                'sku' => $row['sku'],
                'product_name' => $row['product_name'],
                'variant_name' => $row['variant_label'],
                'quantity' => $item->quantity,
                'unit_price' => $row['price'],
            ];
        }

        if ($unavailable) {
            throw new CheckoutException('Some items in your cart are no longer available.', 'items_unavailable', ['items' => $unavailable]);
        }

        $totals = $this->pricing->calculate($lines);
        $number = $this->numbers->fromKey($key);
        $isCod = $in['payment_method'] === Order::COD;
        $ttl = (int) config($isCod ? 'orders.cod_hold_minutes' : 'orders.online_hold_minutes');

        // 2. stock: hold it, or fall back to a pre-order when EVERY line can be pre-ordered and the customer agreed
        $reservation = null;
        $preorder = false;

        try {
            $reservation = $this->inventory->reserve(
                $number,
                'order:'.$key,
                $ttl,
                array_map(fn ($l) => ['variant_id' => $l['variant_id'], 'quantity' => $l['quantity']], $lines)
            );
        } catch (InsufficientStockException $e) {
            $this->assertPreorderPossible($e->shortages, count($lines), (bool) $in['accept_preorder']);
            $preorder = true;
        }

        // 3. save everything
        $createdPreorders = [];

        try {
            if ($preorder) {
                foreach ($lines as $line) {
                    $this->inventory->createPreorder($number, $line['variant_id'], $line['quantity']);
                    $createdPreorders[] = $line['variant_id'];
                }
            }

            $order = DB::transaction(fn () => $this->save($customerId, $key, $number, $in, $lines, $totals, $isCod, $preorder, $ttl, $reservation, $cart));
        } catch (UniqueConstraintViolationException) {
            // a double click: the other request already created this order
            return [Order::where('idempotency_key', $key)->firstOrFail()->load('items', 'payments', 'preorders'), false];
        } catch (\Throwable $e) {
            $this->undoStockHold($reservation, $number, $createdPreorders);

            throw $e;
        }

        return [$order, true];
    }

    private function save(int $customerId, string $key, string $number, array $in, array $lines, array $totals, bool $isCod, bool $preorder, int $ttl, ?array $reservation, $cart): Order
    {
        $order = Order::create([
            'order_number' => $number,
            'type' => $preorder ? Order::TYPE_PREORDER : Order::TYPE_STANDARD,
            'customer_id' => $customerId,
            'status' => $preorder ? Order::AWAITING_STOCK : Order::PENDING,
            'payment_status' => Order::PAYMENT_PENDING,
            'payment_method' => $in['payment_method'],
            'fulfillment_status' => Order::FULFILLMENT_UNFULFILLED,
            'currency' => config('orders.currency'),
            'subtotal' => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'tax_total' => $totals['tax_total'],
            'shipping_total' => $totals['shipping_total'],
            'grand_total' => $totals['grand_total'],
            'billing_address' => $in['billing_address'] ?? $in['shipping_address'],
            'shipping_address' => $in['shipping_address'],
            'customer_name' => $in['contact']['name'],
            'customer_email' => $in['contact']['email'] ?? null,
            'customer_phone' => $in['contact']['phone'],
            'customer_note' => $in['customer_note'] ?? null,
            'idempotency_key' => $key,
            'placed_at' => now(),
            // pre-orders get their deadline when the stock arrives (PreorderService::allocate)
            'hold_expires_at' => $preorder ? null : now()->addMinutes($ttl),
            'inventory_reservation_id' => $reservation['id'] ?? null,
        ]);

        foreach ($lines as $i => $line) {
            $order->items()->create([
                'product_id' => $line['product_id'],
                'variant_id' => $line['variant_id'],
                'sku' => $line['sku'],
                'product_name' => $line['product_name'],
                'variant_name' => $line['variant_name'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_amount' => 0,
                'tax_amount' => $totals['lines'][$i]['tax'],
                'subtotal' => $totals['lines'][$i]['subtotal'],
                'total' => $totals['lines'][$i]['total'],
                'is_preorder' => $preorder,
            ]);
        }

        $order->payments()->create([
            'method' => $in['payment_method'],
            'status' => 'pending',
            'amount' => $totals['grand_total'],
            'currency' => config('orders.currency'),
        ]);

        if ($preorder) {
            $this->preorders->createForOrder($order, $lines);
        }

        $this->events->record($order, 'order.placed', $preorder ? 'Pre-order placed; waiting for stock.' : 'Order placed; stock is held.', 'customer', $customerId);
        $this->publisher->publish($order->load('items'), 'order.placed');

        $cart->update(['status' => 'converted']);

        return $order->load('items', 'payments', 'preorders');
    }

    /**
     * @param  array<int, array{variant_id:int, can_preorder:bool}>  $shortages
     *
     * @throws CheckoutException
     */
    private function assertPreorderPossible(array $shortages, int $lineCount, bool $accepted): void
    {
        $allShort = count($shortages) === $lineCount;
        $allPreorderable = $allShort && collect($shortages)->every(fn ($s) => ! empty($s['can_preorder']));

        if ($allPreorderable && $accepted) {
            return;
        }

        if ($allPreorderable) {
            throw new CheckoutException(
                'These items are out of stock but can be pre-ordered. Send accept_preorder=true to place a pre-order.',
                'preorder_available',
                ['shortages' => $shortages]
            );
        }

        throw new CheckoutException(
            $allShort
                ? 'Some items are out of stock and cannot be pre-ordered.'
                : 'Some items are out of stock. Remove them, or order pre-order items in a separate order.',
            'insufficient_stock',
            ['shortages' => $shortages]
        );
    }

    /** Saving the order failed after stock was held: give it back at once (the hold would also expire by itself). */
    private function undoStockHold(?array $reservation, string $number, array $variantIds): void
    {
        try {
            if ($reservation) {
                $this->inventory->release((int) $reservation['id']);
            }

            foreach ($variantIds as $variantId) {
                $this->inventory->cancelPreorder($number, $variantId);
            }
        } catch (\Throwable $e) {
            Log::warning("Could not undo the stock hold for {$number}: ".$e->getMessage());
        }
    }
}
