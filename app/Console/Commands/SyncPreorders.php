<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Clients\InventoryServiceClient;
use App\Services\PreorderService;
use Illuminate\Console\Command;

/**
 * Safety net for a lost "inventory.preorder.allocated" event: asks Inventory about every order that is still
 * awaiting stock and applies what it says (allocating twice is harmless).
 */
class SyncPreorders extends Command
{
    protected $signature = 'orders:sync-preorders';

    protected $description = 'Ask Inventory which pre-ordered orders already have their stock';

    public function handle(InventoryServiceClient $inventory, PreorderService $preorders): int
    {
        $applied = 0;

        Order::where('status', Order::AWAITING_STOCK)->orderBy('id')->chunkById(50, function ($chunk) use ($inventory, $preorders, &$applied) {
            foreach ($chunk as $order) {
                try {
                    foreach ($inventory->preorders($order->order_number) as $row) {
                        if (($row['status'] ?? null) === 'allocated' && ! empty($row['reservation_id'])) {
                            $preorders->allocate($order->order_number, (int) $row['variant_id'], (int) $row['reservation_id']);
                            $applied++;
                        }
                    }
                } catch (\Throwable $e) {
                    $this->error("{$order->order_number}: {$e->getMessage()}");
                }
            }
        });

        $this->info("Checked awaiting orders; {$applied} allocated line(s) applied.");

        return self::SUCCESS;
    }
}
