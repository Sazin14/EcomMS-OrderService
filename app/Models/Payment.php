<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const PARTIALLY_REFUNDED = 'partially_refunded';
    public const REFUNDED = 'refunded';

    protected $fillable = [
        'order_id',
        'method',
        'status',
        'amount',
        'currency',
        'transaction_reference',
        'paid_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}
