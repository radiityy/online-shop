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
                'proof_image' => 'Data pembayaran tidak ditemukan.',
            ]);
        }

        if ($order->payment_status === 'paid') {
            return back()->withErrors([
                'proof_image' => 'Pembayaran untuk order ini sudah dikonfirmasi.',
            ]);
        }

        if ($order->payment->proof_image) {
            Storage::disk('public')->delete($order->payment->proof_image);
        }

        $path = $validated['proof_image']->store('payment-proofs', 'public');

        $order->payment->update([
            'proof_image' => $path,
            'status' => 'pending',
        ]);

        $order->update([
            'payment_status' => 'pending',
        ]);

        return back()->with('success', 'Bukti pembayaran berhasil diupload. Menunggu konfirmasi admin.');
    }
}