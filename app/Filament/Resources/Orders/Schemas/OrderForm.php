<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Status')
                    ->description('Update payment, order, and shipping progress.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('payment_status')
                                    ->label('Payment Status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'waiting_confirmation' => 'Waiting Confirmation',
                                        'paid' => 'Paid',
                                        'failed' => 'Failed',
                                        'expired' => 'Expired',
                                        'refunded' => 'Refunded',
                                    ])
                                    ->required(),

                                Select::make('order_status')
                                    ->label('Order Status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'processing' => 'Processing',
                                        'packed' => 'Packed',
                                        'shipped' => 'Shipped',
                                        'delivered' => 'Delivered',
                                        'completed' => 'Completed',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->required(),

                                Select::make('shipping_status')
                                    ->label('Shipping Status')
                                    ->options([
                                        'not_shipped' => 'Not Shipped',
                                        'packed' => 'Packed',
                                        'shipped' => 'Shipped',
                                        'delivered' => 'Delivered',
                                        'returned' => 'Returned',
                                    ])
                                    ->required(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Shipping / Tracking')
                    ->description('Fill courier information and tracking number.')
                    ->schema([
                        Group::make()
                            ->relationship('shipment')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('courier')
                                            ->label('Courier')
                                            ->placeholder('JNE / J&T / SiCepat')
                                            ->maxLength(255),

                                        TextInput::make('service')
                                            ->label('Service')
                                            ->placeholder('REG / YES / EZ')
                                            ->maxLength(255),

                                        TextInput::make('tracking_number')
                                            ->label('Tracking Number / Resi')
                                            ->placeholder('Nomor resi')
                                            ->maxLength(255),
                                    ]),

                                Select::make('status')
                                    ->label('Shipment Status')
                                    ->options([
                                        'not_shipped' => 'Not Shipped',
                                        'packed' => 'Packed',
                                        'shipped' => 'Shipped',
                                        'delivered' => 'Delivered',
                                        'returned' => 'Returned',
                                    ])
                                    ->required(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Customer & Address')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('recipient_name')
                                    ->label('Recipient Name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('phone')
                                    ->label('Phone')
                                    ->required()
                                    ->maxLength(20),

                                TextInput::make('province')
                                    ->label('Province')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('city')
                                    ->label('City')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('district')
                                    ->label('District')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('postal_code')
                                    ->label('Postal Code')
                                    ->maxLength(10),

                                Textarea::make('full_address')
                                    ->label('Full Address')
                                    ->required()
                                    ->rows(4)
                                    ->columnSpanFull(),

                                Textarea::make('notes')
                                    ->label('Customer Notes')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}