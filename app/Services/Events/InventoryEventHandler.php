<?php

namespace App\Services\Events;

use App\Services\PreorderService;
use App\Support\RabbitMq\EventHandler;

/**
 * Events InventoryService publishes that OrderService cares about (queue: order.inventory).
 *
 * Needs App\Services\PreorderService::allocate(string $reference, int $variantId, int $reservationId),
 * which marks the pre-order line as allocated and moves the order forward.
 */
class InventoryEventHandler implements EventHandler
{
    public function __construct(private PreorderService $preorders) {}

    public function handle(array $envelope): void
    {
        $data = $envelope['data'] ?? [];

        match ($envelope['type']) {
            // stock arrived and is now held for this pre-order
            'inventory.preorder.allocated' => $this->preorders->allocate(
                (string) $data['reference'],        // = our order number
                (int) $data['variant_id'],
                (int) $data['reservation_id'],
            ),
            default => null,   // a newer producer may send types this version does not know yet
        };
    }
}
