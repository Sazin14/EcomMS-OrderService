<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsIdentity;
use App\Models\Order;
use App\Services\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    use ReadsIdentity;

    public function __construct(private CheckoutService $checkout) {}

    /**
     * POST /api/checkout   header: Idempotency-Key (any unique string per attempt; the same key returns the same order)
     */
    public function store(Request $request)
    {
        $key = (string) $request->header('Idempotency-Key');

        abort_if(strlen($key) < 8 || strlen($key) > 100, 422, 'The Idempotency-Key header is required (8-100 characters).');

        $data = $request->validate([
            'payment_method' => ['required', Rule::in([Order::COD, Order::ONLINE])],
            'shipping_address' => ['required', 'array'],
            'shipping_address.name' => ['required', 'string', 'max:150'],
            'shipping_address.phone' => ['required', 'string', 'max:40'],
            'shipping_address.address' => ['required', 'string', 'max:500'],
            'shipping_address.city' => ['required', 'string', 'max:100'],
            'shipping_address.postcode' => ['nullable', 'string', 'max:20'],
            'shipping_address.country' => ['nullable', 'string', 'size:2'],
            'billing_address' => ['nullable', 'array'],
            'email' => ['nullable', 'email', 'max:190'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'accept_preorder' => ['nullable', 'boolean'],
        ]);

        [$order, $created] = $this->checkout->place($this->customerId($request), [
            'idempotency_key' => $key,
            'payment_method' => $data['payment_method'],
            'shipping_address' => $data['shipping_address'],
            'billing_address' => $data['billing_address'] ?? null,
            'contact' => [
                'name' => $data['shipping_address']['name'],
                'phone' => $data['shipping_address']['phone'],
                'email' => $data['email'] ?? null,
            ],
            'customer_note' => $data['customer_note'] ?? null,
            'accept_preorder' => (bool) ($data['accept_preorder'] ?? false),
        ]);

        return response()->json($order, $created ? 201 : 200);
    }
}
