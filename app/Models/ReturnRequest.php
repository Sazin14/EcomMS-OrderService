<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    public const REQUESTED = 'requested';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const RECEIVED = 'received';     // goods inspected in the warehouse, Inventory told
    public const COMPLETED = 'completed';   // refund created (or nothing to refund)
    public const CANCELLED = 'cancelled';

    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_DELIVERY_FAILED = 'delivery_failed';

    protected $fillable = [
        'order_id', 'reference', 'type', 'status', 'reason', 'customer_note', 'staff_note', 'idempotency_key',
        'requested_at', 'approved_at', 'received_at', 'rejected_at', 'completed_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'received_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }
}
