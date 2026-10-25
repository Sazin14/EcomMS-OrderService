<?php

namespace App\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Preorder;
use App\Services\Concerns\FailsValidation;
use Illuminate\Support\Facades\DB;

/**
 * Picking -> packed -> shipped -> delivered | failed.
 * The whole order ships in ONE parcel (Inventory takes the stock out for the whole order when it hears "order.shipped").
 * Each step that matters to other services publishes its event in the same transaction.
 */
class FulfillmentService
{
    use FailsValidation;

    public function __construct(
        private OrderService $orders,
        private PaymentService $payments,
        private ReturnService $returns,
        private OrderEventService $events,
        private OrderEventPublisher $publisher,
    ) {}

    public function create(Order $order, array $data, ?int $actorId = null): Fulfillment
    {
        return DB::transaction(function () use ($order, $data, $actorId) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== Order::CONFIRMED) {
                $this->fail('order', "Only confirmed orders can be fulfilled (this one is {$order->status}).");
            }

            $fulfillment = $order->fulfillments()->create([
                'status' => Fulfillment::PICKING,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'shipping_method' => $data['shipping_method'] ?? null,
            ]);

            foreach ($order->items as $item) {
                $fulfillment->items()->create([
                    'order_item_id' => $item->id,
                    'variant_id' => $item->variant_id,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                ]);
            }

            $this->orders->transition($order, [Order::CONFIRMED], Order::PROCESSING);
            $this->events->record($order, 'fulfillment.created', 'Picking started.', 'staff', $actorId);

            return $fulfillment->load('items');
        });
    }

    public function pack(Fulfillment $fulfillment, ?int $actorId = null): Fulfillment
    {
        return DB::transaction(function () use ($fulfillment, $actorId) {
            $fulfillment = $this->lock($fulfillment);
            $this->assertStatus($fulfillment, Fulfillment::PICKING, 'packed');

            $fulfillment->update(['status' => Fulfillment::PACKED]);
            $this->events->record($fulfillment->order, 'fulfillment.packed', 'Parcel packed.', 'staff', $actorId);

            return $fulfillment->refresh();
        });
    }

    public function ship(Fulfillment $fulfillment, string $courier, string $trackingNumber, ?string $trackingUrl = null, ?int $actorId = null): Fulfillment
    {
        return DB::transaction(function () use ($fulfillment, $courier, $trackingNumber, $trackingUrl, $actorId) {
            $fulfillment = $this->lock($fulfillment);
            $this->assertStatus($fulfillment, Fulfillment::PACKED, 'shipped');

            $fulfillment->update([
                'status' => Fulfillment::SHIPPED,
                'courier' => $courier,
                'tracking_number' => $trackingNumber,
                'tracking_url' => $trackingUrl,
                'shipped_at' => now(),
            ]);

            $fulfillment->shipment()->create([
                'courier' => $courier,
                'tracking_number' => $trackingNumber,
                'tracking_url' => $trackingUrl,
                'status' => 'shipped',
                'shipped_at' => now(),
            ]);

            $order = $this->orders->transition($fulfillment->order, [Order::PROCESSING], Order::SHIPPED, [
                'shipped_at' => now(),
                'fulfillment_status' => Order::FULFILLMENT_FULFILLED,
            ]);

            $order->preorders()->where('status', Preorder::AVAILABLE)->update(['status' => Preorder::CONVERTED]);

            $this->events->record($order, 'order.shipped', "Shipped via {$courier}.", 'staff', $actorId, ['tracking_number' => $trackingNumber]);
            $this->publisher->publish($order, 'order.shipped', ['carrier' => $courier, 'tracking_number' => $trackingNumber]);

            return $fulfillment->refresh();
        });
    }

    /** $cashCollected: for cash-on-delivery the courier must report that the money was handed over. */
    public function deliver(Fulfillment $fulfillment, bool $cashCollected, ?int $actorId = null): Fulfillment
    {
        return DB::transaction(function () use ($fulfillment, $cashCollected, $actorId) {
            $fulfillment = $this->lock($fulfillment);
            $this->assertStatus($fulfillment, Fulfillment::SHIPPED, 'delivered');

            if ($fulfillment->order->isCod() && ! $cashCollected) {
                $this->fail('cash_collected', 'Cash on delivery: confirm that the cash was collected (cash_collected=true), or report the delivery as failed.');
            }

            $fulfillment->update(['status' => Fulfillment::DELIVERED, 'delivered_at' => now()]);
            $fulfillment->shipment()->update(['status' => 'delivered', 'delivered_at' => now()]);

            $order = $this->orders->transition($fulfillment->order, [Order::SHIPPED], Order::DELIVERED, ['delivered_at' => now()]);

            if ($order->isCod()) {
                $this->payments->markCodCollected($order, $actorId);
            }

            $this->events->record($order, 'order.delivered', 'Delivered to the customer.', 'staff', $actorId);
            $this->publisher->publish($order->refresh(), 'order.delivered');

            return $fulfillment->refresh();
        });
    }

    /** Refused at the door / not reachable. The parcel comes back; a return is opened so the warehouse can receive it. */
    public function deliveryFailed(Fulfillment $fulfillment, string $reason, ?int $actorId = null): Fulfillment
    {
        return DB::transaction(function () use ($fulfillment, $reason, $actorId) {
            $fulfillment = $this->lock($fulfillment);
            $this->assertStatus($fulfillment, Fulfillment::SHIPPED, 'failed');

            $fulfillment->update(['status' => Fulfillment::FAILED]);
            $fulfillment->shipment()->update(['status' => 'failed']);

            $order = $this->orders->transition($fulfillment->order, [Order::SHIPPED], Order::DELIVERY_FAILED);

            $this->events->record($order, 'order.delivery_failed', "Delivery failed: {$reason}", 'staff', $actorId);
            $this->publisher->publish($order, 'order.delivery_failed', ['reason' => $reason]);

            $this->returns->openForFailedDelivery($order, $fulfillment, $reason);

            return $fulfillment->refresh();
        });
    }

    private function lock(Fulfillment $fulfillment): Fulfillment
    {
        return Fulfillment::whereKey($fulfillment->id)->lockForUpdate()->with('order')->firstOrFail();
    }

    private function assertStatus(Fulfillment $fulfillment, string $expected, string $target): void
    {
        if ($fulfillment->status !== $expected) {
            $this->fail('fulfillment', "A fulfillment that is {$fulfillment->status} cannot become {$target} (it must be {$expected}).");
        }
    }
}
