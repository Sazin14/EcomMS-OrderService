<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ReadsIdentity;
use App\Http\Controllers\Controller;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Services\FulfillmentService;
use Illuminate\Http\Request;

class FulfillmentController extends Controller
{
    use ReadsIdentity;

    public function __construct(private FulfillmentService $fulfillments) {}

    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'shipping_method' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json($this->fulfillments->create($order, $data, $this->staffId($request)), 201);
    }

    public function pack(Request $request, Fulfillment $fulfillment)
    {
        return $this->fulfillments->pack($fulfillment, $this->staffId($request));
    }

    public function ship(Request $request, Fulfillment $fulfillment)
    {
        $data = $request->validate([
            'courier' => ['required', 'string', 'max:100'],
            'tracking_number' => ['required', 'string', 'max:150'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
        ]);

        return $this->fulfillments->ship($fulfillment, $data['courier'], $data['tracking_number'], $data['tracking_url'] ?? null, $this->staffId($request));
    }

    public function deliver(Request $request, Fulfillment $fulfillment)
    {
        $data = $request->validate(['cash_collected' => ['nullable', 'boolean']]);

        return $this->fulfillments->deliver($fulfillment, (bool) ($data['cash_collected'] ?? false), $this->staffId($request));
    }

    public function deliveryFailed(Request $request, Fulfillment $fulfillment)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return $this->fulfillments->deliveryFailed($fulfillment, $data['reason'], $this->staffId($request));
    }
}
