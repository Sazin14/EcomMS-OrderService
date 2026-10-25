<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Services\Clients\ProductServiceClient;
use App\Services\Concerns\FailsValidation;
use Illuminate\Support\Facades\DB;

/** One active cart per customer. The cart stores a snapshot for display; checkout prices everything again. */
class CartService
{
    use FailsValidation;

    public function __construct(private ProductServiceClient $products) {}

    public function getOrCreate(int $customerId): Cart
    {
        return Cart::firstOrCreate(
            ['customer_id' => $customerId, 'status' => 'active'],
            ['currency' => config('orders.currency')]
        );
    }

    /** Cart with current prices. If ProductService is down we show the saved prices and say so. */
    public function view(Cart $cart): array
    {
        $cart->load('items');
        $stale = false;

        try {
            $current = $this->products->variants($cart->items->pluck('variant_id')->all());

            foreach ($cart->items as $item) {
                $row = $current[$item->variant_id] ?? null;

                $item->setAttribute('available_to_buy', (bool) ($row['is_active'] ?? false));

                if ($row) {
                    $item->unit_price = $row['price'];   // shown price (not saved here)
                }
            }
        } catch (\Throwable) {
            $stale = true;
        }

        $subtotal = $cart->items->sum(fn ($i) => (float) $i->unit_price * $i->quantity);

        return ['cart' => $cart, 'subtotal' => number_format($subtotal, 2, '.', ''), 'prices_stale' => $stale];
    }

    public function addItem(Cart $cart, int $variantId, int $quantity): CartItem
    {
        $variant = $this->products->variants([$variantId])[$variantId] ?? null;

        if (! $variant || ! $variant['is_active']) {
            $this->fail('variant_id', 'This product is not available.');
        }

        return DB::transaction(function () use ($cart, $variant, $quantity) {
            $item = $cart->items()->where('variant_id', $variant['variant_id'])->lockForUpdate()->first();
            $newQty = ($item?->quantity ?? 0) + $quantity;

            $this->assertQuantity($newQty);

            $snapshot = [
                'product_id' => $variant['product_id'],
                'sku' => $variant['sku'],
                'product_name' => $variant['product_name'],
                'variant_name' => $variant['variant_label'],
                'unit_price' => $variant['price'],
                'quantity' => $newQty,
            ];

            if ($item) {
                $item->update($snapshot);

                return $item->refresh();
            }

            return $cart->items()->create($snapshot + ['variant_id' => $variant['variant_id']]);
        });
    }

    public function updateItem(CartItem $item, int $quantity): CartItem
    {
        $this->assertQuantity($quantity);
        $item->update(['quantity' => $quantity]);

        return $item->refresh();
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }

    private function assertQuantity(int $quantity): void
    {
        $max = (int) config('orders.max_quantity_per_line', 20);

        if ($quantity < 1 || $quantity > $max) {
            $this->fail('quantity', "Quantity must be between 1 and {$max}.");
        }
    }
}
