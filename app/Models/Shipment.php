<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    protected $fillable = [
        'fulfillment_id',
        'courier',
        'tracking_number',
        'tracking_url',
        'status',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'fulfillment_id' => 'integer',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }
}
