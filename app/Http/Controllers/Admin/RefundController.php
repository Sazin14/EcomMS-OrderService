<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ReadsIdentity;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RefundController extends Controller
{
    use ReadsIdentity;

    public function __construct(private RefundService $refunds) {}

    public function index(Request $request)
    {
        return Refund::query()
            ->with('order:id,order_number')
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 30), 100));
    }

    /** A manual refund (a goodwill refund, a wrong charge ...). Returns create theirs by themselves. */
    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(RefundService::METHODS)],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $refund = $this->refunds->create($order, $data['amount'], $data['method'], $data['reason'], null, null, $this->staffId($request));

        return response()->json($refund, 201);
    }

    /** "original" refunds: send to the payment gateway. */
    public function process(Refund $refund)
    {
        return $this->refunds->process($refund);
    }

    /** cash / bank / wallet refunds: staff paid it by hand and record it here. */
    public function complete(Request $request, Refund $refund)
    {
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:150']]);

        return $this->refunds->complete($refund, $data['reference'] ?? null, $this->staffId($request));
    }
}
