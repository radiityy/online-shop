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
        'status',
        'shipped_at',
        'delivered_at',
        'courier_code',
        'tracking_status',
        'tracking_history',
        'tracking_raw_response',
        'last_tracked_at',
    ];

    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'tracking_history' => 'array',
            'tracking_raw_response' => 'array',
            'last_tracked_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}