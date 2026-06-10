<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Summary')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('order_code')
                                    ->label('Order Code')
                                    ->weight('bold'),

                                TextEntry::make('user.name')
                                    ->label('Customer'),

                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime('d M Y H:i'),

                                TextEntry::make('payment_status')
                                    ->label('Payment Status')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', strtoupper($state)))
                                    ->color(fn (string $state): string => match ($state) {
                                        'paid' => 'success',
                                        'failed', 'expired', 'refunded' => 'danger',
                                        'waiting_confirmation' => 'info',
                                        default => 'warning',
                                    }),

                                TextEntry::make('order_status')
                                    ->label('Order Status')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', strtoupper($state)))
                                    ->color(fn (string $state): string => match ($state) {
                                        'completed', 'delivered' => 'success',
                                        'processing', 'packed', 'shipped' => 'info',
                                        'cancelled' => 'danger',
                                        default => 'warning',
                                    }),

                                TextEntry::make('shipping_status')
                                    ->label('Shipping Status')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', strtoupper($state)))
                                    ->color(fn (string $state): string => match ($state) {
                                        'delivered' => 'success',
                                        'shipped', 'packed' => 'info',
                                        'returned' => 'danger',
                                        default => 'warning',
                                    }),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Customer Address')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('recipient_name')
                                    ->label('Recipient'),

                                TextEntry::make('phone')
                                    ->label('Phone'),

                                TextEntry::make('full_address')
                                    ->label('Address')
                                    ->columnSpanFull(),

                                TextEntry::make('district')
                                    ->label('District'),

                                TextEntry::make('city')
                                    ->label('City'),

                                TextEntry::make('province')
                                    ->label('Province'),

                                TextEntry::make('postal_code')
                                    ->label('Postal Code')
                                    ->placeholder('-'),

                                TextEntry::make('notes')
                                    ->label('Notes')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Ordered Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('product_name')
                                            ->label('Product')
                                            ->weight('bold'),

                                        TextEntry::make('variant_name')
                                            ->label('Size')
                                            ->placeholder('-'),

                                        TextEntry::make('quantity')
                                            ->label('Qty'),

                                        TextEntry::make('price')
                                            ->label('Price')
                                            ->money('IDR'),

                                        TextEntry::make('subtotal')
                                            ->label('Subtotal')
                                            ->money('IDR'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Payment')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('payment.payment_method')
                                    ->label('Method')
                                    ->formatStateUsing(fn (?string $state): string => $state ? str_replace('_', ' ', strtoupper($state)) : '-'),

                                TextEntry::make('payment.amount')
                                    ->label('Amount')
                                    ->money('IDR'),

                                TextEntry::make('payment.status')
                                    ->label('Proof Status')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => $state ? str_replace('_', ' ', strtoupper($state)) : '-')
                                    ->color(fn (?string $state): string => match ($state) {
                                        'paid' => 'success',
                                        'failed', 'expired', 'refunded' => 'danger',
                                        'waiting_confirmation' => 'info',
                                        default => 'warning',
                                    }),

                                ImageEntry::make('payment.proof_image')
                                    ->label('Payment Proof')
                                    ->disk('public')
                                    ->visibility('public')
                                    ->height(300)
                                    ->extraImgAttributes([
                                        'class' => 'object-contain',
                                    ])
                                    ->placeholder('No proof uploaded')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Shipment')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('shipment.courier')
                                    ->label('Courier')
                                    ->placeholder('-'),

                                TextEntry::make('shipment.service')
                                    ->label('Service')
                                    ->placeholder('-'),

                                TextEntry::make('shipment.tracking_number')
                                    ->label('Tracking Number')
                                    ->placeholder('-'),

                                TextEntry::make('shipment.status')
                                    ->label('Shipment Status')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => $state ? str_replace('_', ' ', strtoupper($state)) : '-')
                                    ->color(fn (?string $state): string => match ($state) {
                                        'delivered' => 'success',
                                        'shipped', 'packed' => 'info',
                                        'returned' => 'danger',
                                        default => 'warning',
                                    }),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Payment Total')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money('IDR'),

                                TextEntry::make('shipping_cost')
                                    ->label('Shipping Cost')
                                    ->money('IDR'),

                                TextEntry::make('total')
                                    ->label('Total')
                                    ->money('IDR')
                                    ->weight('bold'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}