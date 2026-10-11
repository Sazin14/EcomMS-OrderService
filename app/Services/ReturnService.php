<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\Clients\InventoryServiceClient;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReturnService
{
    public function __construct(
        private OrderNumberService $numbers,
        private OrderEventService $events,
        private InventoryServiceClient $inventory,
    ) {}

    public function request(
        Order $order,
        array $items,
        string $idempotencyKey,
        ?string $reason = null,
        ?int $customerId = null
    ): ReturnRequest {
        $existing = ReturnRequest::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing->load('items');
        }

        if ($order->customer_id !== $customerId && $customerId !== null) {
            throw new RuntimeException('This order does not belong to the customer.');
        }

        if ($order->status === Order::CANCELLED) {
            throw new RuntimeException('Cancelled orders cannot be returned.');
        }

        return DB::transaction(function () use ($order, $items, $idempotencyKey, $reason) {
            $return = $order->returns()->create([
                'reference' => $this->numbers->generateReturnReference(),
                'status' => ReturnRequest::REQUESTED,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'requested_at' => now(),
            ]);

            foreach ($items as $item) {
                $orderItem = $order->items()->findOrFail($item['order_item_id']);

                $return->items()->create([
                    'order_item_id' => $orderItem->id,
                    'variant_id' => $orderItem->variant_id,
                    'sku' => $orderItem->sku,
                    'quantity' => $item['quantity'],
                    'reason' => $item['reason'] ?? $reason,
                    'condition' => null,
                    'refund_amount' => ($orderItem->unit_price * $item['quantity']),
                ]);
            }

            $this->events->record(
                $order,
                'return.requested',
                'Customer return requested.',
                null,
                null,
                ['return_reference' => $return->reference]
            );

            return $return->load('items');
        });
    }

    public function approve(ReturnRequest $return, ?int $staffId = null): ReturnRequest
    {
        if ($return->status !== ReturnRequest::REQUESTED) {
            throw new RuntimeException('Only requested returns can be approved.');
        }

        $return->update([
            'status' => ReturnRequest::APPROVED,
            'approved_at' => now(),
        ]);

        $this->events->record(
            $return->order,
            'return.approved',
            'Return request approved.',
            'staff',
            $staffId
        );

        return $return->refresh();
    }

    public function completeWithInventory(
        ReturnRequest $return,
        array $items,
        string $idempotencyKey,
        ?int $staffId = null
    ): ReturnRequest {
        if ($return->status !== ReturnRequest::APPROVED) {
            throw new RuntimeException('Return must be approved before warehouse receipt.');
        }

        $payload = [
            'reservation_id' => null,
            'reference' => $return->reference,
            'idempotency_key' => $idempotencyKey,
            'reason' => $return->reason,
            'items' => $items,
            'order_id' => $return->order_id,
        ];

        $this->inventory->createStockReturn($payload);

        $return->update([
            'status' => ReturnRequest::COMPLETED,
            'completed_at' => now(),
        ]);

        $this->events->record(
            $return->order,
            'return.completed',
            'Return received and sent to Inventory Service for stock classification.',
            'staff',
            $staffId
        );

        return $return->refresh();
    }
}
