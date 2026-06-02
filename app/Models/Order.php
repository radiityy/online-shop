<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'address_id',
        'order_code',
        'recipient_name',
        'phone',
        'province',
        'city',
        'district',
        'postal_code',
        'full_address',
        'subtotal',
        'shipping_cost',
        'total',
        'payment_status',
        'order_status',
        'shipping_status',
        'stock_released',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'stock_released' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (Order $order): void {
            $order->releaseReservedStockIfNeeded();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function shouldReleaseStock(): bool
    {
        if ($this->stock_released) {
            return false;
        }

        return in_array($this->payment_status, [
            'failed',
            'expired',
            'refunded',
        ], true) || in_array($this->order_status, [
            'cancelled',
        ], true);
    }

    public function releaseReservedStockIfNeeded(): void
    {
        if (! $this->shouldReleaseStock()) {
            return;
        }

        DB::transaction(function (): void {
            $order = self::query()
                ->whereKey($this->id)
                ->lockForUpdate()
                ->first();

            if (! $order || $order->stock_released) {
                return;
            }

            $shouldRelease = in_array($order->payment_status, [
                'failed',
                'expired',
                'refunded',
            ], true) || in_array($order->order_status, [
                'cancelled',
            ], true);

            if (! $shouldRelease) {
                return;
            }

            $order->loadMissing('items.variant');

            foreach ($order->items as $item) {
                $variant = $item->variant()
                    ->lockForUpdate()
                    ->first();

                if (! $variant) {
                    continue;
                }

                $variant->increment('stock', $item->quantity);
            }

            $order->forceFill([
                'stock_released' => true,
            ])->saveQuietly();
        });
    }
}