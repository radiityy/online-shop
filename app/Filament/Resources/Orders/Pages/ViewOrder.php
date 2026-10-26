<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\Actions\ProcessShipmentAction;
use App\Filament\Resources\Orders\Actions\RetryRefundAction;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RetryRefundAction::make(),
            ProcessShipmentAction::make(),
            EditAction::make(),
        ];
    }
}