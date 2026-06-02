<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        static::updating(function (Order $order): void {
            if (! $order->stock_released) {
                return;
            }

            $activePaymentStatuses = [
                'pending',
                'waiting_confirmation',
                'paid',
            ];

            $activeOrderStatuses = [
                'pending',
                'processing',
                'packed',
                'shipped',
                'delivered',
                'completed',
            ];

            $activeShippingStatuses = [
                'not_shipped',
                'packed',
                'shipped',
                'delivered',
            ];

            $isTryingToReactivate =
                (
                    $order->isDirty('payment_status')
                    && in_array($order->payment_status, $activePaymentStatuses, true)
                )
                || (
                    $order->isDirty('order_status')
                    && in_array($order->order_status, $activeOrderStatuses, true)
                )
                || (
                    $order->isDirty('shipping_status')
                    && in_array($order->shipping_status, $activeShippingStatuses, true)
                );

            if ($isTryingToReactivate) {
                throw ValidationException::withMessages([
                    'payment_status' => 'This order has already been cancelled, failed, expired, or refunded. Please ask the customer to place a new order.',
                ]);
            }
        });

        static::updated(function (Order $order): void {
            $order->syncRelatedStatuses();
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

    public function syncRelatedStatuses(): void
    {
        if ($this->wasChanged('payment_status') && $this->payment) {
            $this->payment->updateQuietly([
                'status' => $this->payment_status,
            ]);
        }

        if ($this->wasChanged('shipping_status') && $this->shipment) {
            $this->shipment->updateQuietly([
                'status' => $this->shipping_status,
            ]);
        }
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