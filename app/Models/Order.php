<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    // status
    public const PENDING = 'pending';                 // placed; waiting for staff confirmation (COD) or payment (online)
    public const AWAITING_STOCK = 'awaiting_stock';   // pre-order: waiting for the stock to arrive
    public const CONFIRMED = 'confirmed';
    public const PROCESSING = 'processing';           // being picked / packed
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const DELIVERY_FAILED = 'delivery_failed';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    // type
    public const TYPE_STANDARD = 'standard';
    public const TYPE_PREORDER = 'preorder';

    // payment method
    public const COD = 'cod';
    public const ONLINE = 'online';

    // payment_status
    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_AUTHORIZED = 'authorized';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';
    public const PAYMENT_REFUNDED = 'refunded';

    // fulfillment_status
    public const FULFILLMENT_UNFULFILLED = 'unfulfilled';
    public const FULFILLMENT_PARTIAL = 'partially_fulfilled';
    public const FULFILLMENT_FULFILLED = 'fulfilled';
    public const FULFILLMENT_RETURNED = 'returned';

    protected $fillable = [
        'order_number', 'type', 'customer_id', 'status', 'payment_status', 'payment_method', 'fulfillment_status',
        'currency', 'subtotal', 'discount_total', 'tax_total', 'shipping_total', 'grand_total',
        'billing_address', 'shipping_address', 'customer_name', 'customer_email', 'customer_phone',
        'customer_note', 'internal_note', 'idempotency_key',
        'placed_at', 'confirmed_at', 'cancelled_at', 'completed_at', 'shipped_at', 'delivered_at', 'hold_expires_at',
        'inventory_reservation_id', 'needs_attention', 'attention_note',
    ];

    protected $hidden = ['idempotency_key'];

    protected $casts = [
        'customer_id' => 'integer',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'needs_attention' => 'boolean',
        'placed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'hold_expires_at' => 'datetime',
    ];

    public function isPreorder(): bool
    {
        return $this->type === self::TYPE_PREORDER;
    }

    public function isCod(): bool
    {
        return $this->payment_method === self::COD;
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function preorders(): HasMany
    {
        return $this->hasMany(Preorder::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->latest('id');
    }
}
