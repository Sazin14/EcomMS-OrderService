<?php

namespace App\Services;

use App\Models\Order;

class OrderEventService
{
    public function record(
        Order $order,
        string $type,
        string $description,
        ?string $actorType = null,
        ?int $actorId = null,
        array $metadata = []
    ): void {
        $order->events()->create([
            'type' => $type,
            'description' => $description,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'metadata' => $metadata ?: null,
        ]);
    }
}
