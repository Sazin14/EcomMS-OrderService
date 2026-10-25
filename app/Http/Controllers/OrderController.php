<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsIdentity;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

/** The customer's own orders. */
class OrderController extends Controller
{
    use ReadsIdentity;

    public function __construct(private OrderService $orders, private PaymentService $payments) {}

    public function index(Request $request)
    {
        return Order::query()
            ->where('customer_id', $this->customerId($request))
            ->with('items')
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 20), 100));
    }

    public function show(Request $request, Order $order)
    {
        return $this->ownOrder($request, $order)
            ->load('items', 'payments', 'fulfillments.shipment', 'returns.items', 'refunds', 'preorders', 'events');
    }

    /** Customers can cancel until staff confirm / the payment arrives. After that: call support, or return the goods. */
    public function cancel(Request $request, Order $order)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $order = $this->ownOrder($request, $order);

        abort_unless(
            in_array($order->status, [Order::PENDING, Order::AWAITING_STOCK, Order::CANCELLED], true),
            422,
            'This order is already confirmed. Please contact support to cancel it.'
        );

        return $this->orders->cancel($order, $data['reason'] ?? 'Cancelled by the customer.', 'customer', $this->customerId($request));
    }

    /** Start (or restart after a failure) the online payment: returns the page the customer should be sent to. */
    public function pay(Request $request, Order $order)
    {
        return response()->json($this->payments->startOnline($this->ownOrder($request, $order)), 201);
    }
}
