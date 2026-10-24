<?php

namespace App\Services;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MidtransService
{
    public function isConfigured(): bool
    {
        return filled(config('services.midtrans.server_key'));
    }

    public function methods(): array
    {
        return collect(config('services.midtrans.methods', []))
            ->filter(fn (array $method) => $method['enabled'] ?? false)
            ->all();
    }

    public function method(string $key): ?array
    {
        return $this->methods()[$key] ?? null;
    }

    public function feeFor(string $methodKey, int $amount): int
    {
        $method = $this->method($methodKey);

        if (! $method) {
            throw new RuntimeException('Metode pembayaran tidak tersedia.');
        }

        return (int) $method['fee_flat']
            + (int) ceil($amount * ((float) $method['fee_percent']) / 100);
    }

    public function charge(
        string $methodKey,
        string $gatewayOrderId,
        int $grossAmount,
        array $customer = [],
        ?string $returnUrl = null,
    ): array {
        $method = $this->method($methodKey);

        if (! $method) {
            throw new RuntimeException('Metode pembayaran tidak tersedia.');
        }

        $body = [
            'payment_type' => $method['payment_type'],
            'transaction_details' => [
                'order_id' => $gatewayOrderId,
                'gross_amount' => $grossAmount,
            ],
            'custom_expiry' => [
                'expiry_duration' => (int) config('services.midtrans.expiry_minutes', 60),
                'unit' => 'minute',
            ],
        ];

        $customerDetails = array_filter([
            'first_name' => $customer['name'] ?? null,
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ]);

        if ($customerDetails !== []) {
            $body['customer_details'] = $customerDetails;
        }

        if ($method['payment_type'] === 'bank_transfer') {
            $body['bank_transfer'] = ['bank' => $method['bank']];
        }

        if ($method['payment_type'] === 'qris') {
            $body['qris'] = ['acquirer' => 'gopay'];
        }

        if ($method['payment_type'] === 'gopay' && $returnUrl) {
            $body['gopay'] = [
                'enable_callback' => true,
                'callback_url' => $returnUrl,
            ];
        }

        $data = $this->send('post', '/v2/charge', $body, ['200', '201']);

        return $this->normalize($data);
    }

    public function status(string $gatewayOrderId): array
    {
        $data = $this->send(
            'get',
            '/v2/'.rawurlencode($gatewayOrderId).'/status',
            [],
            ['200', '201', '202', '407'],
        );

        return $this->normalize($data);
    }

    public function cancel(string $gatewayOrderId): array
    {
        $data = $this->send(
            'post',
            '/v2/'.rawurlencode($gatewayOrderId).'/cancel',
            [],
            ['200', '201'],
        );

        return $this->normalize($data);
    }

    public function verifySignature(array $payload): bool
    {
        $serverKey = (string) config('services.midtrans.server_key');

        if ($serverKey === '') {
            return false;
        }

        $expected = hash('sha512',
            (string) ($payload['order_id'] ?? '')
            .(string) ($payload['status_code'] ?? '')
            .(string) ($payload['gross_amount'] ?? '')
            .$serverKey
        );

        return hash_equals($expected, (string) ($payload['signature_key'] ?? ''));
    }

    public function mapStatus(?string $transactionStatus, ?string $fraudStatus = null): string
    {
        return match ($transactionStatus) {
            'settlement' => Payment::STATUS_PAID,
            'capture' => $fraudStatus === 'challenge' ? Payment::STATUS_PENDING : Payment::STATUS_PAID,
            'pending' => Payment::STATUS_PENDING,
            'deny', 'failure' => Payment::STATUS_FAILED,
            'cancel' => Payment::STATUS_CANCELLED,
            'expire' => Payment::STATUS_EXPIRED,
            'refund', 'partial_refund', 'chargeback', 'partial_chargeback' => Payment::STATUS_REFUNDED,
            default => Payment::STATUS_PENDING,
        };
    }

    private function send(string $verb, string $path, array $body, array $okCodes): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Pembayaran belum dikonfigurasi.');
        }

        try {
            $client = $this->client();
            $response = $verb === 'get' ? $client->get($path) : $client->post($path, $body);
        } catch (ConnectionException $e) {
            Log::warning('Midtrans tidak dapat dihubungi', ['path' => $path, 'error' => $e->getMessage()]);

            throw new RuntimeException('Layanan pembayaran sedang tidak dapat dihubungi. Coba lagi sebentar.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            Log::warning('Respons Midtrans tidak valid', ['path' => $path, 'http' => $response->status()]);

            throw new RuntimeException('Respons layanan pembayaran tidak valid.');
        }

        $code = (string) ($data['status_code'] ?? $response->status());

        if (! in_array($code, $okCodes, true)) {
            Log::warning('Midtrans menolak permintaan', [
                'path' => $path,
                'http' => $response->status(),
                'status_code' => $code,
                'response' => $data,
            ]);

            $reason = $data['status_message']
                ?? (is_array($data['error_messages'] ?? null) ? implode(' ', $data['error_messages']) : null)
                ?? (is_array($data['validation_messages'] ?? null) ? implode(' ', $data['validation_messages']) : null)
                ?? ($data['error'] ?? null)
                ?? 'kesalahan tidak diketahui (HTTP '.$response->status().')';

            throw new RuntimeException('Pembayaran ditolak oleh gateway: '.$reason.'.');
        }

        return $data;
    }

    private function client(): PendingRequest
    {
        $baseUrl = config('services.midtrans.is_production')
            ? config('services.midtrans.production_url')
            : config('services.midtrans.sandbox_url');

        return Http::baseUrl($baseUrl)
            ->withBasicAuth((string) config('services.midtrans.server_key'), '')
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.midtrans.timeout', 15));
    }

    private function normalize(array $data): array
    {
        $actions = collect($data['actions'] ?? []);

        $expiresAt = null;

        if (! empty($data['expiry_time'])) {
            $expiresAt = Carbon::parse($data['expiry_time'], 'Asia/Jakarta');
        }

        return [
            'transaction_id' => $data['transaction_id'] ?? null,
            'gateway_status' => $data['transaction_status'] ?? null,
            'fraud_status' => $data['fraud_status'] ?? null,
            'va_number' => $data['permata_va_number'] ?? data_get($data, 'va_numbers.0.va_number'),
            'qr_string' => $data['qr_string'] ?? null,
            'qr_url' => $actions->firstWhere('name', 'generate-qr-code')['url'] ?? null,
            'deeplink_url' => $actions->firstWhere('name', 'deeplink-redirect')['url'] ?? null,
            'expires_at' => $expiresAt,
            'raw' => $data,
        ];
    }
}