<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Preorder extends Model
{
    public const PENDING = 'pending';
    public const AVAILABLE = 'available';
    public const CONVERTED = 'converted';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'reference',
        'customer_id',
        'variant_id',
        'sku',
        'quantity',
        'status',
        'expected_at',
        'order_id',
        'cancelled_at',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'variant_id' => 'integer',
        'quantity' => 'integer',
        'expected_at' => 'datetime',
        'order_id' => 'integer',
        'cancelled_at' => 'datetime',
    ];
}
