<?php

namespace App\Services;

use App\Exceptions\RefundAccountException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RefundAccountService
{
    public function __construct(private readonly MidtransService $midtrans)
    {
    }

    /**
     * A payment needs a bank account from the customer when it waits for a refund that
     * the gateway cannot send by itself (Virtual Account). QRIS and GoPay refund automatically.
     */
    public function requiresAccount(Payment $payment): bool
    {
        return $payment->status === Payment::STATUS_REFUND_REQUIRED
            && ! $this->midtrans->supportsRefund($payment->payment_type);
    }

    public function banks(): array
    {
        return (array) config('refund.banks', []);
    }

    public function lengthError(string $bankCode, string $number): ?string
    {
        $bank = $this->banks()[$bankCode] ?? null;

        if ($bank === null) {
            return 'Please choose a bank from the list.';
        }

        $length = strlen($number);

        if ($length >= $bank['min'] && $length <= $bank['max']) {
            return null;
        }

        $digits = $bank['min'] === $bank['max']
            ? $bank['min'].' digits'
            : 'between '.$bank['min'].' and '.$bank['max'].' digits';

        return $bank['label'].' account numbers have '.$digits.'.';
    }

    /**
     * @param  array{bank_code: string, account_number: string, account_holder: string}  $data
     */
    public function submit(Order $order, Payment $payment, User $user, array $data, ?string $ip): RefundAccount
    {
        return DB::transaction(function () use ($order, $payment, $user, $data, $ip): RefundAccount {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->order_id !== $order->id || ! $this->requiresAccount($payment)) {
                throw new RefundAccountException('This payment does not need a refund account.');
            }

            $existing = RefundAccount::query()
                ->where('payment_id', $payment->id)
                ->lockForUpdate()
                ->first();

            if ($existing?->isLocked() || $existing?->isCleared()) {
                throw new RefundAccountException('We have already started your refund, so the account can no longer be changed.');
            }

            $holder = Str::squish($data['account_holder']);
            $matches = $this->nameMatches($holder, [$user->name, $order->recipient_name]);

            return RefundAccount::query()->updateOrCreate(
                ['payment_id' => $payment->id],
                [
                    'user_id' => $user->id,
                    'bank_code' => $data['bank_code'],
                    'account_number' => $data['account_number'],
                    'account_last4' => substr($data['account_number'], -4),
                    'account_holder' => $holder,
                    'name_matches' => $matches,
                    'needs_review' => ! $matches,
                    'submitted_ip' => $ip,
                    'submitted_at' => now(),
                ],
            );
        });
    }

    /**
     * Admin has seen the account and starts the transfer. From here the customer cannot change it.
     */
    public function lock(RefundAccount $account): void
    {
        if ($account->locked_at === null) {
            $account->forceFill(['locked_at' => now()])->save();
        }
    }

    /**
     * The holder name matches when every word of the shorter name appears in the other
     * ("Budi" vs "Budi Santoso"), or when the two are almost the same text (small typos).
     * A mismatch is never a rejection, only a flag for the admin.
     *
     * @param  array<int, string|null>  $customerNames
     */
    public function nameMatches(string $holder, array $customerNames): bool
    {
        $holderWords = $this->words($holder);

        if ($holderWords === []) {
            return false;
        }

        foreach ($customerNames as $name) {
            $words = $this->words((string) $name);

            if ($words === []) {
                continue;
            }

            $shared = count(array_intersect($holderWords, $words));

            if ($shared === min(count($holderWords), count($words))) {
                return true;
            }

            similar_text(implode(' ', $holderWords), implode(' ', $words), $percent);

            if ($percent >= 85) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the customer sees on the order page. The full number is never included.
     */
    public function presentFor(Order $order): array
    {
        $payments = $order->payments()
            ->where('status', Payment::STATUS_REFUND_REQUIRED)
            ->get()
            ->filter(fn (Payment $payment): bool => $this->requiresAccount($payment));

        if ($payments->isEmpty()) {
            return [];
        }

        $accounts = RefundAccount::query()
            ->whereIn('payment_id', $payments->pluck('id'))
            ->get()
            ->keyBy('payment_id');

        return $payments->map(function (Payment $payment) use ($accounts): array {
            /** @var RefundAccount|null $account */
            $account = $accounts->get($payment->id);

            $state = match (true) {
                $account === null => 'needed',
                $account->isLocked() => 'locked',
                default => 'submitted',
            };

            return [
                'payment_id' => $payment->id,
                'amount' => (float) $payment->amount,
                'method' => (string) config("services.midtrans.methods.{$payment->payment_method}.label", 'Virtual Account'),
                'state' => $state,
                'bank_code' => $account?->bank_code,
                'bank_label' => $account?->bankLabel(),
                'masked' => $account?->masked(),
                'holder' => $account?->account_holder,
                'can_edit' => $account !== null && ! $account->isLocked(),
                'estimate' => (string) config('refund.estimate'),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array{code: string, label: string, min: int, max: int}>
     */
    public function bankOptions(): array
    {
        return collect($this->banks())
            ->map(fn (array $bank, string $code): array => [
                'code' => $code,
                'label' => $bank['label'],
                'min' => (int) $bank['min'],
                'max' => (int) $bank['max'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function words(string $value): array
    {
        $clean = Str::of(Str::ascii(mb_strtolower($value)))
            ->replaceMatches('/[^a-z\s]/', ' ')
            ->squish();

        return collect(explode(' ', (string) $clean))
            ->filter(fn (string $word): bool => strlen($word) > 1)
            ->values()
            ->all();
    }
}