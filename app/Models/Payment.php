<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUND_REQUIRED = 'refund_required';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'order_id',
        'payment_method',
        'gateway',
        'payment_type',
        'bank',
        'gateway_order_id',
        'transaction_id',
        'va_number',
        'qr_string',
        'qr_url',
        'deeplink_url',
        'amount',
        'fee',
        'status',
        'gateway_status',
        'fraud_status',
        'proof_image',
        'payment_reference',
        'paid_at',
        'expires_at',
        'last_webhook_at',
        'raw_response',
    ];

    protected $hidden = [
        'raw_response',
        'qr_string',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_webhook_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function needsRefund(): bool
    {
        return $this->status === self::STATUS_REFUND_REQUIRED;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [
            self::STATUS_PAID,
            self::STATUS_FAILED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
            self::STATUS_REFUND_REQUIRED,
            self::STATUS_REFUNDED,
        ], true);
    }

    public function hasExpired(): bool
    {
        return $this->isPending()
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }
}