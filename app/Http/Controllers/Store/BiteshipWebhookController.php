<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Services\ShipmentFulfillmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BiteshipWebhookController extends Controller
{
    public function __construct(
        private readonly ShipmentFulfillmentService $fulfillment,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->hasValidSecret($request)) {
            Log::warning('Webhook Biteship: secret tidak valid', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $payload = $request->json()->all() ?: $request->all();

        $applied = $this->fulfillment->applyWebhook($payload);

        return response()->json(['message' => $applied ? 'OK' : 'Ignored.']);
    }

    private function hasValidSecret(Request $request): bool
    {
        $header = (string) config('services.biteship.webhook_header');
        $secret = (string) config('services.biteship.webhook_secret');

        if ($header === '' || $secret === '') {
            Log::error('Webhook Biteship: BITESHIP_WEBHOOK_SECRET belum diatur.');

            return false;
        }

        return hash_equals($secret, (string) $request->header($header, ''));
    }
}