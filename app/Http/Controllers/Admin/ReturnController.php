<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ReadsIdentity;
use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\RefundService;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReturnController extends Controller
{
    use ReadsIdentity;

    public function __construct(private ReturnService $returns) {}

    public function index(Request $request)
    {
        return ReturnRequest::query()
            ->with('items', 'order:id,order_number,customer_name,payment_method')
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 30), 100));
    }

    public function show(ReturnRequest $return)
    {
        return $return->load('items', 'order', 'order.refunds');
    }

    public function approve(Request $request, ReturnRequest $return)
    {
        $data = $request->validate(['staff_note' => ['nullable', 'string', 'max:2000']]);

        return $this->returns->approve($return, $data['staff_note'] ?? null, $this->staffId($request));
    }

    public function reject(Request $request, ReturnRequest $return)
    {
        $data = $request->validate(['staff_note' => ['required', 'string', 'max:2000']]);

        return $this->returns->reject($return, $data['staff_note'], $this->staffId($request));
    }

    /** The parcel arrived and was inspected. items: [{return_item_id, condition: resellable|damaged}] for EVERY line. */
    public function receive(Request $request, ReturnRequest $return)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.return_item_id' => ['required', 'integer'],
            'items.*.condition' => ['required', Rule::in(['resellable', 'damaged'])],
        ]);

        return $this->returns->receive($return, $data['items'], $this->staffId($request));
    }

    /** Creates the refund. amount defaults to the sum of the returned lines. */
    public function complete(Request $request, ReturnRequest $return)
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0'],
            'method' => ['nullable', Rule::in(RefundService::METHODS)],
        ]);

        return $this->returns->complete(
            $return,
            isset($data['amount']) ? (float) $data['amount'] : null,
            $data['method'] ?? null,
            $this->staffId($request)
        );
    }
}
