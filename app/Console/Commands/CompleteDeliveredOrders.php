<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

class CompleteDeliveredOrders extends Command
{
    protected $signature = 'orders:complete-delivered';

    protected $description = 'Mark delivered orders completed once their return window is over';

    public function handle(OrderService $orders): int
    {
        $cutoff = now()->subDays((int) config('orders.return_window_days', 7));
        $count = 0;

        Order::where('status', Order::DELIVERED)->where('delivered_at', '<', $cutoff)->orderBy('id')->chunkById(100, function ($chunk) use ($orders, &$count) {
            foreach ($chunk as $order) {
                $orders->complete($order);
                $count++;
            }
        });

        $this->info("Completed {$count} order(s).");

        return self::SUCCESS;
    }
}
