<?php

namespace App\Services;

use Illuminate\Support\Str;

class OrderNumberService
{
    /**
     * The order number is derived from the customer's idempotency key, so a retried checkout always gets the
     * SAME number. That keeps the order, the stock hold and the pre-orders in Inventory (all found by this number)
     * consistent even when the first attempt crashed halfway.
     */
    public function fromKey(string $idempotencyKey): string
    {
        return 'ORD-'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 10));
    }

    public function generate(): string
    {
        return 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    public function generateReturnReference(): string
    {
        return 'RET-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    public function generatePreorderReference(): string
    {
        return 'PRE-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
