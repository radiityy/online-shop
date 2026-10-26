<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundAccount extends Model
{
    protected $fillable = [
        'payment_id',
        'user_id',
        'bank_code',
        'account_number',
        'account_holder',
        'account_last4',
        'name_matches',
        'needs_review',
        'submitted_ip',
        'submitted_at',
        'locked_at',
        'cleared_at',
    ];

    // Never sent to the frontend by accident (toArray, Inertia, JSON).
    protected $hidden = [
        'account_number',
        'account_holder',
        'submitted_ip',
    ];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'account_holder' => 'encrypted',
            'name_matches' => 'boolean',
            'needs_review' => 'boolean',
            'submitted_at' => 'datetime',
            'locked_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function isCleared(): bool
    {
        return $this->cleared_at !== null;
    }

    public function bankLabel(): string
    {
        return (string) config("refund.banks.{$this->bank_code}.label", strtoupper($this->bank_code));
    }

    public function masked(): string
    {
        return '•••• '.$this->account_last4;
    }

    /**
     * Data minimisation: once the refund is done the number and the holder name are erased.
     * The bank, the last four digits and the timestamps stay as the audit trail.
     *
     * @param  iterable<int>  $paymentIds
     */
    public static function wipeFor(iterable $paymentIds): void
    {
        $ids = collect($paymentIds)->all();

        if ($ids === []) {
            return;
        }

        static::query()
            ->whereIn('payment_id', $ids)
            ->whereNull('cleared_at')
            ->update([
                'account_number' => null,
                'account_holder' => null,
                'submitted_ip' => null,
                'cleared_at' => now(),
            ]);
    }
}