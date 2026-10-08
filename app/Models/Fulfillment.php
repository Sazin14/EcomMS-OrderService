<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fulfillment extends Model
{
    public const UNFULFILLED = 'unfulfilled';
    public const PICKING = 'picking';
    public const PACKED = 'packed';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'order_id',
        'status',
        'warehouse_id',
        'tracking_number',
        'courier',
        'shipping_method',
        'tracking_url',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'warehouse_id' => 'integer',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FulfillmentItem::class);
    }
}
