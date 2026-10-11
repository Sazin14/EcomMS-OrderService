<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request)
    {
        return Order::query()
            ->with('items')
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when(
                $request->attributes->get('user_type') === 'customer',
                fn ($q) => $q->where('customer_id', $request->attributes->get('user_id'))
            )
            ->latest()
            ->paginate(min((int) $request->query('per_page', 30), 100));
    }

    public function show(Order $order)
    {
        return response()->json(
            $order->load('items', 'payments.attempts', 'fulfillments.items', 'returns.items', 'refunds', 'events')
        );
    }

    public function confirm(Request $request, Order $order)
    {
        $actorId = $request->attributes->get('user_type') === 'staff'
            ? (int) $request->attributes->get('user_id')
            : null;

        return response()->json($this->orders->confirm($order, $actorId));
    }

    public function cancel(Request $request, Order $order)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $actorId = $request->attributes->get('user_type') === 'staff'
            ? (int) $request->attributes->get('user_id')
            : null;

        return response()->json(
            $this->orders->cancel($order, $data['reason'], $actorId)
        );
    }

    public function process(Order $order)
    {
        return response()->json($this->orders->markProcessing($order));
    }
}
