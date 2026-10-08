<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';

    protected $fillable = [
        'order_id',
        'return_request_id',
        'payment_id',
        'amount',
        'currency',
        'method',
        'status',
        'provider_reference',
        'reason',
        'processed_at',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'return_request_id' => 'integer',
        'payment_id' => 'integer',
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
