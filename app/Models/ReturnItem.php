<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    protected $fillable = [
        'return_request_id',
        'order_item_id',
        'variant_id',
        'sku',
        'quantity',
        'reason',
        'condition',
        'refund_amount',
    ];

    protected $casts = [
        'return_request_id' => 'integer',
        'order_item_id' => 'integer',
        'variant_id' => 'integer',
        'quantity' => 'integer',
        'refund_amount' => 'decimal:2',
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
