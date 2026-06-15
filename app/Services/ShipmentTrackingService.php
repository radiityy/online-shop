<?php

namespace App\Services;

use App\Models\Shipment;
use Illuminate\Support\Facades\Http;

class ShipmentTrackingService
{
    public function sync(Shipment $shipment): Shipment
    {
        if (! $shipment->tracking_number || ! $shipment->courier_code) {
            return $shipment;
        }

        $response = Http::timeout(15)
            ->retry(2, 500)
            ->get(config('services.binderbyte.base_url') . '/track', [
                'api_key' => config('services.binderbyte.key'),
                'courier' => $shipment->courier_code,
                'awb' => $shipment->tracking_number,
            ]);

        $json = $response->json();

        if (! $response->successful() || data_get($json, 'status') !== 200) {
            $shipment->forceFill([
                'tracking_raw_response' => $json ?: ['error' => $response->body()],
                'last_tracked_at' => now(),
            ])->save();

            return $shipment;
        }

        $shipment->forceFill([
            'tracking_status' => data_get($json, 'data.summary.status'),
            'tracking_history' => data_get($json, 'data.history', []),
            'tracking_raw_response' => $json,
            'last_tracked_at' => now(),
        ])->save();

        return $shipment;
    }
}