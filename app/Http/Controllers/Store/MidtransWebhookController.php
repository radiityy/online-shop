<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\MidtransService;
use App\Services\PaymentSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    public function __construct(
        private readonly MidtransService $midtrans,
        private readonly PaymentSettlementService $settlement,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->json()->all() ?: $request->all();

        if (! $this->midtrans->verifySignature($payload)) {
            Log::warning('Webhook Midtrans: signature tidak valid', [
                'order_id' => $payload['order_id'] ?? null,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $payment = Payment::query()
            ->where('gateway_order_id', (string) ($payload['order_id'] ?? ''))
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Ignored.']);
        }

        $this->settlement->apply($payment, [
            'transaction_id' => $payload['transaction_id'] ?? null,
            'gateway_status' => $payload['transaction_status'] ?? null,
            'fraud_status' => $payload['fraud_status'] ?? null,
            'raw' => $payload,
        ]);

        return response()->json(['message' => 'OK']);
    }
}