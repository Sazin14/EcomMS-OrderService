<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentItem extends Model
{
    protected $fillable = [
        'fulfillment_id',
        'order_item_id',
        'variant_id',
        'sku',
        'quantity',
    ];

    protected $casts = [
        'fulfillment_id' => 'integer',
        'order_item_id' => 'integer',
        'variant_id' => 'integer',
        'quantity' => 'integer',
    ];

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
