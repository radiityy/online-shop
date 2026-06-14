<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Services\ShipmentTrackingService;
use Illuminate\Console\Command;

class SyncShipmentTracking extends Command
{
    protected $signature = 'shipments:sync-tracking {--id=}';

    protected $description = 'Sync shipment tracking status from tracking provider.';

    public function handle(ShipmentTrackingService $service): int
    {
        $query = Shipment::query()
            ->whereNotNull('tracking_number')
            ->whereNotNull('courier_code')
            ->where(function ($query) {
                $query->whereNull('tracking_status')
                    ->orWhere('tracking_status', '!=', 'DELIVERED');
            });

        if ($this->option('id')) {
            $query->whereKey($this->option('id'));
        }

        $query->chunkById(25, function ($shipments) use ($service) {
            foreach ($shipments as $shipment) {
                $service->sync($shipment);

                $this->info("Synced shipment #{$shipment->id}");
            }
        });

        return self::SUCCESS;
    }
}