<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function getOrCreate(?int $customerId, ?string $sessionId): Cart
    {
        return Cart::query()
            ->where('status', 'active')
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when(!$customerId && $sessionId, fn ($q) => $q->where('session_id', $sessionId))
            ->with('items')
            ->first()
            ?? Cart::create([
                'customer_id' => $customerId,
                'session_id' => $sessionId,
                'currency' => 'BDT',
                'status' => 'active',
            ]);
    }

    public function addItem(Cart $cart, array $data): CartItem
    {
        return DB::transaction(function () use ($cart, $data) {
            $item = $cart->items()->where('variant_id', $data['variant_id'])->first();

            if ($item) {
                $item->increment('quantity', $data['quantity']);
                $item->refresh();
                return $item;
            }

            return $cart->items()->create($data);
        });
    }

    public function updateItem(CartItem $item, int $quantity): CartItem
    {
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
}
