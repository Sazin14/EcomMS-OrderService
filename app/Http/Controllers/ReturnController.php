<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\ReturnService;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function __construct(private ReturnService $returns) {}

    public function index(Request $request)
    {
        return ReturnRequest::query()
            ->with('order', 'items')
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(min((int) $request->query('per_page', 30), 100));
    }

    public function store(Request $request, Order $order)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['nullable', 'string', 'max:100'],
        ]);

        $customerId = $request->attributes->get('user_type') === 'customer'
            ? (int) $request->attributes->get('user_id')
            : null;

        $return = $this->returns->request(
            $order,
            $data['items'],
            $request->header('Idempotency-Key') ?? '',
            $data['reason'] ?? null,
            $customerId
        );

        return response()->json($return, 201);
    }

    public function approve(Request $request, ReturnRequest $return)
    {
        $staffId = $request->attributes->get('user_type') === 'staff'
            ? (int) $request->attributes->get('user_id')
            : null;

        return response()->json($this->returns->approve($return, $staffId));
    }

    public function complete(Request $request, ReturnRequest $return)
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer'],
            'items.*.sku' => ['required', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.condition' => ['required', 'in:resellable,damaged'],
            'items.*.warehouse_id' => ['nullable', 'integer'],
        ]);

        $staffId = $request->attributes->get('user_type') === 'staff'
            ? (int) $request->attributes->get('user_id')
            : null;

        return response()->json(
            $this->returns->completeWithInventory(
                $return,
                $data['items'],
                $data['idempotency_key'],
                $staffId
            )
        );
    }
}
