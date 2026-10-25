<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReadsIdentity;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\ReturnService;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    use ReadsIdentity;

    public function __construct(private ReturnService $returns) {}

    public function index(Request $request)
    {
        return ReturnRequest::query()
            ->whereHas('order', fn ($q) => $q->where('customer_id', $this->customerId($request)))
            ->with('items', 'order:id,order_number')
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 20), 100));
    }

    /** POST /api/orders/{order}/returns   header: Idempotency-Key */
    public function store(Request $request, Order $order)
    {
        $key = (string) $request->header('Idempotency-Key');

        abort_if(strlen($key) < 8 || strlen($key) > 100, 422, 'The Idempotency-Key header is required (8-100 characters).');

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.reason' => ['nullable', 'string', 'max:100'],
        ]);

        $return = $this->returns->request(
            $this->ownOrder($request, $order),
            $this->customerId($request),
            $data['items'],
            $key,
            $data['reason'] ?? null,
            $data['note'] ?? null
        );

        return response()->json($return, 201);
    }
}
