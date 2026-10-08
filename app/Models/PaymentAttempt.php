<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    protected $fillable = [
        'payment_id',
        'provider',
        'status',
        'amount',
        'provider_reference',
        'failure_code',
        'failure_message',
        'request_id',
        'processed_at',
    ];

    protected $casts = [
        'payment_id' => 'integer',
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
