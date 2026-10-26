<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Models\Order;

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
                                        'refund_required', 'failed', 'expired' => 'danger',
                                        'refunded' => 'gray',
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
                                        'refund_required', 'failed', 'expired' => 'danger',
                                        'refunded' => 'gray',
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

                Section::make('Payment Attempts')
                    ->description('Every payment attempt for this order. Payments marked Refund Required still need their money returned to the customer.')
                    ->schema([
                        RepeatableEntry::make('payments')
                            ->label('')
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('payment_method')
                                            ->label('Method')
                                            ->formatStateUsing(fn (?string $state): string => $state ? str_replace('_', ' ', strtoupper($state)) : '-'),

                                        TextEntry::make('gateway_order_id')
                                            ->label('Gateway Order ID')
                                            ->placeholder('-'),

                                        TextEntry::make('amount')
                                            ->label('Amount')
                                            ->money('IDR'),

                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->formatStateUsing(fn (?string $state): string => $state ? str_replace('_', ' ', strtoupper($state)) : '-')
                                            ->color(fn (?string $state): string => match ($state) {
                                                'paid' => 'success',
                                                'refund_required', 'failed' => 'danger',
                                                'refunded', 'cancelled', 'expired' => 'gray',
                                                default => 'warning',
                                            }),

                                        TextEntry::make('paid_at')
                                            ->label('Paid At')
                                            ->dateTime('d M Y H:i')
                                            ->placeholder('-'),

                                        TextEntry::make('refund_reference')
                                            ->label('Refund Reference')
                                            ->placeholder('-'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Refund Account')
                    ->description('Bank account the customer gave for a manual refund. The number is erased once the refund is marked as completed.')
                    ->visible(fn (Order $record): bool => $record->refundAccounts()->exists())
                    ->schema([
                        RepeatableEntry::make('refundAccounts')
                            ->label('')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('bank_code')
                                            ->label('Bank')
                                            ->formatStateUsing(fn (?string $state): string => (string) config("refund.banks.{$state}.label", strtoupper((string) $state))),

                                        TextEntry::make('account_number')
                                            ->label('Account Number')
                                            ->copyable()
                                            ->placeholder('Erased'),

                                        TextEntry::make('account_holder')
                                            ->label('Account Holder')
                                            ->copyable()
                                            ->placeholder('Erased'),

                                        TextEntry::make('needs_review')
                                            ->label('Name Check')
                                            ->badge()
                                            ->formatStateUsing(fn (bool $state): string => $state ? 'DIFFERENT, REVIEW' : 'MATCHES')
                                            ->color(fn (bool $state): string => $state ? 'danger' : 'success'),

                                        TextEntry::make('submitted_at')
                                            ->label('Submitted')
                                            ->dateTime('d M Y H:i')
                                            ->placeholder('-'),

                                        TextEntry::make('submitted_ip')
                                            ->label('Submitted From IP')
                                            ->placeholder('-'),

                                        TextEntry::make('locked_at')
                                            ->label('Locked At')
                                            ->dateTime('d M Y H:i')
                                            ->placeholder('Not locked yet'),
                                    ]),
                            ])
                            ->columnSpanFull(),
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

                                TextEntry::make('shipment.biteship_status')
                                    ->label('Biteship Status')
                                    ->formatStateUsing(fn (?string $state): string => $state ? str_replace('_', ' ', strtoupper($state)) : '-')
                                    ->placeholder('-'),

                                TextEntry::make('shipment.biteship_price')
                                    ->label('Biteship Cost')
                                    ->money('IDR')
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