<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per pre-ordered order line. Created by checkout; Inventory tells us (by event) when stock is held for it. */
class Preorder extends Model
{
    public const PENDING = 'pending';        // waiting for stock
    public const AVAILABLE = 'available';    // stock is held for it
    public const CONVERTED = 'converted';    // shipped
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'reference', 'customer_id', 'variant_id', 'sku', 'quantity', 'status', 'expected_at',
        'order_id', 'cancelled_at', 'reservation_id', 'allocated_at',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'variant_id' => 'integer',
        'quantity' => 'integer',
        'order_id' => 'integer',
        'reservation_id' => 'integer',
        'expected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'allocated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
