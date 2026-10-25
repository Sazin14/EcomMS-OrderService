<?php

namespace App\Exceptions;

use RuntimeException;

/** Inventory answered 409: these variants cannot be reserved. */
class InsufficientStockException extends RuntimeException
{
    /** @param array<int, array{variant_id:int, requested:int, available:int, can_preorder:bool, expected_restock_at:?string}> $shortages */
    public function __construct(public array $shortages)
    {
        parent::__construct('Insufficient stock.');
    }
}
