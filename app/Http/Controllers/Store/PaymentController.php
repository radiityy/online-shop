<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;

class PaymentController extends Controller
{
    public function uploadProof(Request $request, string $orderCode): RedirectResponse
    {
        $validated = $request->validate([
            'proof_image' => [
                'required',
                File::image()
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(2048),
                'dimensions:max_width=6000,max_height=6000',
            ],
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

        if ($order->order_status === 'cancelled') {
            return back()->withErrors([
                'proof_image' => 'This order has been cancelled and cannot receive payment proof.',
            ]);
        }

        if ($order->shipping_status === 'returned') {
            return back()->withErrors([
                'proof_image' => 'This order has been returned and cannot receive payment proof.',
            ]);
        }

        $oldProofImage = $order->payment->proof_image;
        $newProofImage = $validated['proof_image']->store('payment-proofs', 'public');

        try {
            $order->payment->update([
                'proof_image' => $newProofImage,
                'status' => 'waiting_confirmation',
            ]);

            $order->update([
                'payment_status' => 'waiting_confirmation',
            ]);

            if ($oldProofImage) {
                Storage::disk('public')->delete($oldProofImage);
            }
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newProofImage);

            throw $exception;
        }

        return back()->with('success', 'Payment proof uploaded. We are checking your payment now.');
    }
}