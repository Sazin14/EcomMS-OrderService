<?php

namespace App\Services;

use App\Models\Order;
use App\Services\Clients\InventoryServiceClient;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    public function __construct(
        private InventoryServiceClient $inventory,
        private OrderEventService $events,
    ) {}

    public function confirm(Order $order, ?int $actorId = null): Order
    {
        if (!in_array($order->status, [Order::PENDING, Order::CONFIRMED], true)) {
            throw new RuntimeException('Order cannot be confirmed from its current status.');
        }

        return DB::transaction(function () use ($order, $actorId) {
            // Inventory reservation id should be persisted in a future
            // reservation_id column on orders once the Inventory API contract
            // is finalized.
            $order->update([
                'status' => Order::CONFIRMED,
                'payment_status' => Order::PAYMENT_PAID,
                'confirmed_at' => $order->confirmed_at ?? now(),
            ]);

            $this->events->record(
                $order,
                'order.confirmed',
                'Order confirmed.',
                $actorId ? 'staff' : 'system',
                $actorId
            );

            return $order->refresh();
        });
    }

    public function cancel(Order $order, string $reason, ?int $actorId = null): Order
    {
        if (in_array($order->status, [Order::COMPLETED, Order::CANCELLED], true)) {
            throw new RuntimeException('Order cannot be cancelled from its current status.');
        }

        return DB::transaction(function () use ($order, $reason, $actorId) {
            $order->update([
                'status' => Order::CANCELLED,
                'cancelled_at' => now(),
                'internal_note' => trim(($order->internal_note ? $order->internal_note . "\n" : '') . 'Cancellation: ' . $reason),
            ]);

            $this->events->record(
                $order,
                'order.cancelled',
                'Order cancelled: ' . $reason,
                $actorId ? 'staff' : 'system',
                $actorId
            );

            return $order->refresh();
        });
    }

    public function markProcessing(Order $order): Order
    {
        if ($order->status !== Order::CONFIRMED) {
            throw new RuntimeException('Only confirmed orders can enter processing.');
        }

        $order->update(['status' => Order::PROCESSING]);

        $this->events->record(
            $order,
            'order.processing',
            'Order moved to processing.'
        );

        return $order->refresh();
    }
}
