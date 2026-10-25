<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

/**
 * Safety net. Normally Inventory publishes "inventory.reservation.expired" and OrderService cancels the order.
 * If that event was ever lost, this command cancels any unconfirmed / unpaid order whose deadline has passed.
 * (It is also the ONLY thing that ends pre-orders nobody confirmed or paid after their stock arrived.)
 */
class CancelExpiredOrders extends Command
{
    protected $signature = 'orders:cancel-expired';

    protected $description = 'Cancel pending orders whose hold has expired';

    public function handle(OrderService $orders): int
    {
        $grace = (int) config('orders.expiry_grace_minutes', 2);
        $count = 0;

        Order::query()
            ->where('status', Order::PENDING)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<', now()->subMinutes($grace))
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use ($orders, &$count) {
                foreach ($chunk as $order) {
                    try {
                        $orders->cancel($order, 'Not confirmed or paid in time.');
                        $count++;
                    } catch (\Throwable $e) {
                        $this->error("{$order->order_number}: {$e->getMessage()}");
                    }
                }
            });

        $this->info("Cancelled {$count} expired order(s).");

        return self::SUCCESS;
    }
}
