<?php

namespace App\Services;

use App\Exceptions\ShippingException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BiteshipService
{
    private const AREA_SEARCH_TTL = 86400;

    private const AREA_TTL = 21600;

    private const FAILURE_MESSAGE = 'Ongkir belum bisa dihitung saat ini. Coba lagi beberapa saat.';

    public function searchAreas(string $input): array
    {
        $input = trim($input);

        $areas = Cache::remember(
            'biteship:areas:' . sha1(mb_strtolower($input)),
            self::AREA_SEARCH_TTL,
            function () use ($input) {
                $json = $this->send('get', '/v1/maps/areas', [
                    'countries' => 'ID',
                    'input' => $input,
                    'type' => 'single',
                ]);

                return collect(data_get($json, 'areas', []))
                    ->map(fn ($area) => $this->normalizeArea($area))
                    ->filter(fn (array $area) => $area['id'] !== '')
                    ->values()
                    ->all();
            }
        );

        $this->rememberAreas($areas);

        return $areas;
    }

    public function findKnownArea(string $areaId): ?array
    {
        $area = Cache::get($this->areaKey($areaId));

        return is_array($area) ? $area : null;
    }

    public function rates(string $originAreaId, string $destinationAreaId, array $items, int $insuranceValue = 0): array
    {
        $payload = [
            'origin_area_id' => $originAreaId,
            'destination_area_id' => $destinationAreaId,
            'couriers' => $this->couriers(),
            'items' => $items,
        ];

        if ($insuranceValue > 0 && $this->insuranceEnabled()) {
            $payload['courier_insurance'] = $insuranceValue;
        }

        return Cache::remember(
            'biteship:rates:' . sha1(json_encode($payload)),
            max(0, (int) config('services.biteship.rates_cache_seconds', 600)),
            fn () => $this->fetchRates($payload)
        );
    }

    private function fetchRates(array $payload): array
    {
        $json = $this->send('post', '/v1/rates/couriers', $payload);

        $preferred = strtolower((string) config('services.biteship.preferred_courier'));

        return collect(data_get($json, 'pricing', []))
            ->map(fn ($pricing) => $this->normalizeOption($pricing))
            ->filter()
            ->sortBy([
                fn (array $a, array $b) => (int) ($b['courier_code'] === $preferred) <=> (int) ($a['courier_code'] === $preferred),
                fn (array $a, array $b) => $a['total'] <=> $b['total'],
            ])
            ->values()
            ->all();
    }

    private function send(string $method, string $path, array $data): array
    {
        $key = (string) config('services.biteship.key');

        if ($key === '') {
            Log::error('Biteship API key belum diatur.');

            throw new ShippingException(self::FAILURE_MESSAGE);
        }

        try {
            $response = $this->client($key)->{$method}($path, $data);
        } catch (ConnectionException $e) {
            Log::warning('Biteship tidak dapat dihubungi.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            throw new ShippingException(self::FAILURE_MESSAGE);
        }

        $json = $response->json();

        if (! $response->successful() || data_get($json, 'success') !== true) {
            Log::warning('Biteship menolak permintaan.', [
                'path' => $path,
                'status' => $response->status(),
                'code' => data_get($json, 'code'),
                'error' => data_get($json, 'error'),
            ]);

            throw new ShippingException(self::FAILURE_MESSAGE);
        }

        return $json;
    }

    private function client(string $key): PendingRequest
    {
        return Http::baseUrl((string) config('services.biteship.base_url'))
            ->withHeaders(['authorization' => $key])
            ->acceptJson()
            ->timeout((int) config('services.biteship.timeout', 10))
            ->retry(2, 300, fn ($exception) => $exception instanceof ConnectionException, throw: false);
    }

    private function normalizeArea(mixed $area): array
    {
        $postalCode = trim((string) data_get($area, 'postal_code', ''));

        return [
            'id' => (string) data_get($area, 'id', ''),
            'name' => (string) data_get($area, 'name', ''),
            'province' => (string) data_get($area, 'administrative_division_level_1_name', ''),
            'city' => (string) data_get($area, 'administrative_division_level_2_name', ''),
            'district' => (string) data_get($area, 'administrative_division_level_3_name', ''),
            'postal_code' => $postalCode === '' ? null : $postalCode,
        ];
    }

    private function normalizeOption(mixed $pricing): ?array
    {
        $courierCode = strtolower((string) data_get($pricing, 'courier_code', ''));
        $serviceCode = (string) data_get($pricing, 'courier_service_code', '');
        $price = data_get($pricing, 'price');

        if ($courierCode === '' || $serviceCode === '' || ! is_numeric($price)) {
            return null;
        }

        $total = (int) round((float) $price);
        $insuranceFee = (int) round((float) data_get($pricing, 'insurance_fee', 0));

        return [
            'key' => $courierCode . ':' . $serviceCode,
            'courier_code' => $courierCode,
            'courier_name' => (string) data_get($pricing, 'courier_name', $courierCode),
            'service_code' => $serviceCode,
            'service_name' => (string) data_get($pricing, 'courier_service_name', $serviceCode),
            'description' => (string) data_get($pricing, 'description', ''),
            'duration' => (string) data_get($pricing, 'duration', ''),
            'shipping_cost' => max(0, $total - $insuranceFee),
            'insurance_fee' => $insuranceFee,
            'total' => $total,
        ];
    }

    private function rememberAreas(array $areas): void
    {
        $entries = [];

        foreach ($areas as $area) {
            $entries[$this->areaKey($area['id'])] = $area;
        }

        if ($entries !== []) {
            Cache::putMany($entries, self::AREA_TTL);
        }
    }

    private function areaKey(string $areaId): string
    {
        return 'biteship:area:' . sha1($areaId);
    }

    private function couriers(): string
    {
        $codes = array_filter(array_map(
            'trim',
            explode(',', strtolower((string) config('services.biteship.couriers')))
        ));

        return implode(',', $codes);
    }

    private function insuranceEnabled(): bool
    {
        return (bool) config('services.biteship.insurance');
    }
}
