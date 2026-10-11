<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $carts,
        private CheckoutService $checkout,
    ) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'shipping_address' => ['required', 'array'],
            'billing_address' => ['nullable', 'array'],
            'payment_method' => ['required', 'string', 'max:40'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'shipping' => ['nullable', 'numeric', 'min:0'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $customerId = $request->attributes->get('user_type') === 'customer'
            ? (int) $request->attributes->get('user_id')
            : null;

        $cart = $this->carts->getOrCreate(
            $customerId,
            $request->header('X-Cart-Session')
        )->load('items');

        $order = $this->checkout->createOrder(
            $cart,
            $data['shipping_address'],
            $data['billing_address'] ?? null,
            $request->header('Idempotency-Key') ?? '',
            $data['payment_method'],
            (float) ($data['discount'] ?? 0),
            (float) ($data['tax'] ?? 0),
            (float) ($data['shipping'] ?? 0),
            $data['customer_note'] ?? null,
        );

        return response()->json($order, 201);
    }
}
