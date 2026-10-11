<?php

namespace App\Http\Controllers;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Services\FulfillmentService;
use Illuminate\Http\Request;

class FulfillmentController extends Controller
{
    public function __construct(private FulfillmentService $fulfillment) {}

    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'shipping_method' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(
            $this->fulfillment->create($order, $data),
            201
        );
    }

    public function ship(Request $request, Fulfillment $fulfillment)
    {
        $data = $request->validate([
            'courier' => ['required', 'string', 'max:100'],
            'tracking_number' => ['required', 'string', 'max:150'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
        ]);

        return response()->json(
            $this->fulfillment->ship(
                $fulfillment,
                $data['courier'],
                $data['tracking_number'],
                $data['tracking_url'] ?? null
            )
        );
    }

    public function fulfillInventory(Request $request, Order $order)
    {
        $data = $request->validate([
            'reservation_id' => ['required', 'integer'],
        ]);

        return response()->json(
            $this->fulfillment->fulfillInventory(
                $order,
                $data['reservation_id']
            )
        );
    }
}
