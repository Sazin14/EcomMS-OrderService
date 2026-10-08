<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $carts) {}

    public function show(Request $request)
    {
        $customerId = $request->attributes->get('user_type') === 'customer'
            ? (int) $request->attributes->get('user_id')
            : null;

        $cart = $this->carts->getOrCreate(
            $customerId,
            $request->header('X-Cart-Session')
        );

        return response()->json($cart->load('items'));
    }

    public function addItem(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['required', 'integer'],
            'sku' => ['required', 'string', 'max:100'],
            'product_name' => ['required', 'string', 'max:255'],
            'variant_name' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $customerId = $request->attributes->get('user_type') === 'customer'
            ? (int) $request->attributes->get('user_id')
            : null;

        $cart = $this->carts->getOrCreate(
            $customerId,
            $request->header('X-Cart-Session')
        );

        return response()->json(
            $this->carts->addItem($cart, $data),
            201
        );
    }

    public function updateItem(Request $request, CartItem $item)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        return response()->json(
            $this->carts->updateItem($item, $data['quantity'])
        );
    }

    public function removeItem(CartItem $item)
    {
        $this->carts->removeItem($item);

        return response()->json(['message' => 'Cart item removed.']);
    }
}
