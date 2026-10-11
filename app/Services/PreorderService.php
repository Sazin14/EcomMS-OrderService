<?php

namespace App\Services;

use App\Models\Preorder;
use Illuminate\Support\Facades\DB;

class PreorderService
{
    public function __construct(private OrderNumberService $numbers) {}

    public function create(
        int $customerId,
        int $variantId,
        string $sku,
        int $quantity,
        ?string $expectedAt = null
    ): Preorder {
        return DB::transaction(function () use (
            $customerId,
            $variantId,
            $sku,
            $quantity,
            $expectedAt
        ) {
            return Preorder::create([
                'reference' => $this->numbers->generatePreorderReference(),
                'customer_id' => $customerId,
                'variant_id' => $variantId,
                'sku' => $sku,
                'quantity' => $quantity,
                'status' => Preorder::PENDING,
                'expected_at' => $expectedAt,
            ]);
        });
    }

    public function cancel(Preorder $preorder): Preorder
    {
        $preorder->update([
            'status' => Preorder::CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $preorder->refresh();
    }
}
