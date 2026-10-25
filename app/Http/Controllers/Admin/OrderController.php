<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ReadsIdentity;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ReadsIdentity;

    public function __construct(private OrderService $orders) {}

    /** ?status= &payment_status= &type=preorder &needs_attention=1 &q=ORD-1234 / name / phone */
    public function index(Request $request)
    {
        return Order::query()
            ->with('items')
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('payment_status'), fn ($q, $v) => $q->where('payment_status', $v))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->boolean('needs_attention'), fn ($q) => $q->where('needs_attention', true))
            ->when($request->query('q'), function ($q, $term) {
                $q->where(function ($w) use ($term) {
                    $w->where('order_number', 'ilike', "%{$term}%")
                        ->orWhere('customer_phone', 'like', "%{$term}%")
                        ->orWhere('customer_name', 'ilike', "%{$term}%");
                });
            })
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 30), 100));
    }

    public function show(Order $order)
    {
        return $order->load('items', 'payments.attempts', 'fulfillments.items', 'fulfillments.shipment', 'returns.items', 'refunds', 'preorders', 'events');
    }

    /** COD: staff phoned the customer and the order is real. */
    public function confirm(Request $request, Order $order)
    {
        return $this->orders->confirm($order, $this->staffId($request));
    }

    public function cancel(Request $request, Order $order)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->orders->cancel($order, $data['reason'], 'staff', $this->staffId($request));
    }

    /** Clears the "needs attention" flag after a person dealt with it. */
    public function resolveAttention(Order $order)
    {
        $order->update(['needs_attention' => false]);

        return $order;
    }
}
