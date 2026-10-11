<?php

namespace App\Http\Controllers;

use App\Models\Preorder;
use App\Services\PreorderService;
use Illuminate\Http\Request;

class PreorderController extends Controller
{
    public function __construct(private PreorderService $preorders) {}

    public function index(Request $request)
    {
        return Preorder::query()
            ->when(
                $request->attributes->get('user_type') === 'customer',
                fn ($q) => $q->where('customer_id', $request->attributes->get('user_id'))
            )
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(min((int) $request->query('per_page', 30), 100));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'sku' => ['required', 'string', 'max:100'],
            'quantity' => ['required', 'integer', 'min:1'],
            'expected_at' => ['nullable', 'date'],
        ]);

        $customerId = (int) $request->attributes->get('user_id');

        return response()->json(
            $this->preorders->create(
                $customerId,
                $data['variant_id'],
                $data['sku'],
                $data['quantity'],
                $data['expected_at'] ?? null
            ),
            201
        );
    }

    public function destroy(Preorder $preorder)
    {
        return response()->json(
            $this->preorders->cancel($preorder)
        );
    }
}
