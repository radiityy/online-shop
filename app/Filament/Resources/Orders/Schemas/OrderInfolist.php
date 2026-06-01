<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('address.id')
                    ->label('Address')
                    ->placeholder('-'),
                TextEntry::make('order_code'),
                TextEntry::make('recipient_name'),
                TextEntry::make('phone'),
                TextEntry::make('province'),
                TextEntry::make('city'),
                TextEntry::make('district'),
                TextEntry::make('postal_code')
                    ->placeholder('-'),
                TextEntry::make('full_address')
                    ->columnSpanFull(),
                TextEntry::make('subtotal')
                    ->numeric(),
                TextEntry::make('shipping_cost')
                    ->money(),
                TextEntry::make('total')
                    ->numeric(),
                TextEntry::make('payment_status')
                    ->badge(),
                TextEntry::make('order_status')
                    ->badge(),
                TextEntry::make('shipping_status')
                    ->badge(),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
