<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Order;
use Illuminate\Http\Request;

/** Reads who is calling (set by the auth.jwt middleware). */
trait ReadsIdentity
{
    protected function customerId(Request $request): int
    {
        return (int) $request->attributes->get('user_id');
    }

    protected function staffId(Request $request): ?int
    {
        return $request->attributes->get('user_type') === 'staff' ? (int) $request->attributes->get('user_id') : null;
    }

    /** A customer may only touch their own order; anything else looks like "not found". */
    protected function ownOrder(Request $request, Order $order): Order
    {
        abort_unless($order->customer_id === $this->customerId($request), 404, 'Order not found.');

        return $order;
    }
}
