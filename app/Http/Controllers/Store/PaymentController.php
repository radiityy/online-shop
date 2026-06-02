<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function uploadProof(Request $request, string $orderCode): RedirectResponse
    {
        $validated = $request->validate([
            'proof_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $order = Order::query()
            ->with('payment')
            ->where('order_code', $orderCode)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (! $order->payment) {
            return back()->withErrors([
                'proof_image' => 'Payment data was not found for this order.',
            ]);
        }

        if (in_array($order->payment_status, ['paid', 'failed', 'expired', 'refunded'], true)) {
            return back()->withErrors([
                'proof_image' => 'Payment proof upload is no longer available for this order.',
            ]);
        }

        if (in_array($order->order_status, ['cancelled'], true)) {
            return back()->withErrors([
                'proof_image' => 'This order has been cancelled and cannot receive payment proof.',
            ]);
        }

        if (in_array($order->shipping_status, ['returned'], true)) {
            return back()->withErrors([
                'proof_image' => 'This order has been returned and cannot receive payment proof.',
            ]);
        }

        if ($order->payment->proof_image) {
            Storage::disk('public')->delete($order->payment->proof_image);
        }

        $path = $validated['proof_image']->store('payment-proofs', 'public');

        $order->payment->update([
            'proof_image' => $path,
            'status' => 'waiting_confirmation',
        ]);

        $order->update([
            'payment_status' => 'waiting_confirmation',
        ]);

        return back()->with('success', 'Payment proof uploaded. We are checking your payment now.');
    }
}