<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsIdentity;
use App\Models\Preorder;
use Illuminate\Http\Request;

/** Pre-orders are created by checkout; this only lists the customer's own. Cancel the order to cancel a pre-order. */
class PreorderController extends Controller
{
    use ReadsIdentity;

    public function index(Request $request)
    {
        return Preorder::query()
            ->where('customer_id', $this->customerId($request))
            ->with('order:id,order_number,status')
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 20), 100));
    }
}
