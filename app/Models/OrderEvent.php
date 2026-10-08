<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEvent extends Model
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'type',
        'description',
        'actor_type',
        'actor_id',
        'metadata',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'actor_id' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
