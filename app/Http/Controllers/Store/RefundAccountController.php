<?php

namespace App\Http\Controllers\Store;

use App\Exceptions\RefundAccountException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderNotificationService;
use App\Services\RefundAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class RefundAccountController extends Controller
{
    public function __construct(
        private readonly RefundAccountService $accounts,
        private readonly OrderNotificationService $notifications,
    ) {
    }

    public function store(Request $request, string $orderCode, Payment $payment): RedirectResponse
    {
        // Only the logged-in owner of the order, anyone else gets a 404.
        $order = Order::query()
            ->where('order_code', $orderCode)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        abort_unless($payment->order_id === $order->id, 404);

        $request->merge([
            'account_number' => preg_replace('/[\s.\-]+/', '', (string) $request->input('account_number')),
        ]);

        $data = $request->validate([
            'bank_code' => ['required', 'string', Rule::in(array_keys($this->accounts->banks()))],
            'account_number' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'account_holder' => ['required', 'string', 'min:3', 'max:100', "regex:/^[\pL\s.'\-]+$/u"],
            'confirmed' => ['accepted'],
        ], [
            'account_number.regex' => 'The account number can only contain digits.',
            'account_holder.regex' => 'The account holder name can only contain letters and spaces.',
            'confirmed.accepted' => 'Please confirm that the account details are correct.',
        ]);

        if ($error = $this->accounts->lengthError($data['bank_code'], $data['account_number'])) {
            throw ValidationException::withMessages(['account_number' => $error]);
        }

        $wasNew = ! $payment->refundAccount()->exists();

        try {
            $this->accounts->submit($order, $payment, Auth::user(), $data, $request->ip());
        } catch (RefundAccountException $exception) {
            return redirect()->route('orders.show', $orderCode)->with('error', $exception->getMessage());
        }

        if ($wasNew) {
            try {
                $this->notifications->notifyRefundAccountReceived($order, $payment);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->route('orders.show', $orderCode)
            ->with('success', 'Thank you, We received your bank account and will return your money to it.');
    }
}