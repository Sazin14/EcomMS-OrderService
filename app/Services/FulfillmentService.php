<?php

namespace App\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Services\Clients\InventoryServiceClient;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FulfillmentService
{
    public function __construct(
        private InventoryServiceClient $inventory,
        private OrderEventService $events,
    ) {}

    public function create(Order $order, array $data): Fulfillment
    {
        if (!in_array($order->status, [Order::CONFIRMED, Order::PROCESSING], true)) {
            throw new RuntimeException('Order is not ready for fulfillment.');
        }

        return DB::transaction(function () use ($order, $data) {
            $fulfillment = $order->fulfillments()->create([
                'status' => Fulfillment::PICKING,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'shipping_method' => $data['shipping_method'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $orderItem = $order->items()->findOrFail($item['order_item_id']);

                $fulfillment->items()->create([
                    'order_item_id' => $orderItem->id,
                    'variant_id' => $orderItem->variant_id,
                    'sku' => $orderItem->sku,
                    'quantity' => $item['quantity'],
                ]);
            }

            $order->update(['status' => Order::PROCESSING]);

            $this->events->record(
                $order,
                'fulfillment.created',
                'Fulfillment created.'
            );

            return $fulfillment->load('items');
        });
    }

    public function ship(
        Fulfillment $fulfillment,
        string $courier,
        string $trackingNumber,
        ?string $trackingUrl = null
    ): Fulfillment {
        if ($fulfillment->status !== Fulfillment::PACKED) {
            throw new RuntimeException('Only packed fulfillments can be shipped.');
        }

        DB::transaction(function () use ($fulfillment, $courier, $trackingNumber, $trackingUrl) {
            $fulfillment->update([
                'status' => Fulfillment::SHIPPED,
                'courier' => $courier,
                'tracking_number' => $trackingNumber,
                'tracking_url' => $trackingUrl,
                'shipped_at' => now(),
            ]);

            $fulfillment->order->update([
                'fulfillment_status' => Order::FULFILLMENT_FULFILLED,
            ]);

            $this->events->record(
                $fulfillment->order,
                'fulfillment.shipped',
                "Order shipped via {$courier}.",
                null,
                null,
                ['tracking_number' => $trackingNumber]
            );
        });

        return $fulfillment->refresh();
    }

    public function fulfillInventory(Order $order, int $reservationId): array
    {
        $result = $this->inventory->fulfill($reservationId);

        $this->events->record(
            $order,
            'inventory.fulfilled',
            'Inventory reservation fulfilled.'
        );

        return $result;
    }
}
