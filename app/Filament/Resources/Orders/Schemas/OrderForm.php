<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Information')
                    ->schema([
                        TextInput::make('order_code')
                            ->label('Order Code')
                            ->disabled()
                            ->dehydrated(false),

                        Select::make('payment_status')
                            ->label('Payment Status')
                            ->options([
                                'pending' => 'Pending',
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
                            ])
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Customer & Address')
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
                            ->required()
                            ->maxLength(255),

                        TextInput::make('city')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('district')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('postal_code')
                            ->label('Postal Code')
                            ->maxLength(10),

                        Textarea::make('full_address')
                            ->label('Full Address')
                            ->required()
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Customer Notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Payment')
                    ->relationship('payment')
                    ->schema([
                        Select::make('status')
                            ->label('Payment Confirmation')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'failed' => 'Failed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required(),

                        TextInput::make('payment_method')
                            ->label('Payment Method')
                            ->default('manual_transfer')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(false),

                        FileUpload::make('proof_image')
                            ->label('Payment Proof')
                            ->disk('public')
                            ->directory('payment-proofs')
                            ->image()
                            ->imageEditor()
                            ->openable()
                            ->downloadable(),

                        TextInput::make('payment_reference')
                            ->label('Payment Reference')
                            ->maxLength(255),

                        DateTimePicker::make('paid_at')
                            ->label('Paid At'),
                    ])
                    ->columns(2),

                Section::make('Shipment')
                    ->relationship('shipment')
                    ->schema([
                        TextInput::make('courier')
                            ->label('Courier')
                            ->placeholder('JNE / J&T / SiCepat')
                            ->maxLength(255),

                        TextInput::make('service')
                            ->label('Service')
                            ->placeholder('REG / YES / Cargo')
                            ->maxLength(255),

                        TextInput::make('tracking_number')
                            ->label('Tracking Number')
                            ->maxLength(255),

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

                        DateTimePicker::make('shipped_at')
                            ->label('Shipped At'),

                        DateTimePicker::make('delivered_at')
                            ->label('Delivered At'),
                    ])
                    ->columns(2),

                Section::make('Price Summary')
                    ->schema([
                        TextInput::make('subtotal')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('shipping_cost')
                            ->label('Shipping Cost')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('total')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(3),
            ]);
    }
}