<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'courier',
        'service',
        'tracking_number',
        'biteship_order_id',
        'biteship_status',
        'biteship_price',
        'status',
        'shipped_at',
        'delivered_at',
        'courier_code',
        'tracking_status',
        'tracking_history',
        'tracking_raw_response',
        'last_tracked_at',
        'estimated_packed_at',
        'estimated_shipped_at',
        'estimated_delivered_from',
        'estimated_delivered_to',
    ];

    protected $hidden = [
        'tracking_raw_response',
        'biteship_order_id',
        'biteship_status',
        'biteship_price',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'tracking_history' => 'array',
            'tracking_raw_response' => 'array',
            'biteship_price' => 'integer',
            'last_tracked_at' => 'datetime',
            'estimated_packed_at' => 'datetime',
            'estimated_shipped_at' => 'datetime',
            'estimated_delivered_from' => 'date',
            'estimated_delivered_to' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}