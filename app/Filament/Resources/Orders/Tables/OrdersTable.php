<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_code')
                    ->label('Order Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', strtoupper($state)))
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'failed', 'expired', 'refunded' => 'danger',
                        'waiting_confirmation' => 'info',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('order_status')
                    ->label('Order')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', strtoupper($state)))
                    ->color(fn (string $state): string => match ($state) {
                        'completed', 'delivered' => 'success',
                        'processing', 'packed', 'shipped' => 'info',
                        'cancelled' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('shipping_status')
                    ->label('Shipping')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', strtoupper($state)))
                    ->color(fn (string $state): string => match ($state) {
                        'delivered' => 'success',
                        'shipped', 'packed' => 'info',
                        'returned' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('shipment.tracking_number')
                    ->label('Resi')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'pending' => 'Pending',
                        'waiting_confirmation' => 'Waiting Confirmation',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'expired' => 'Expired',
                        'refunded' => 'Refunded',
                    ]),

                SelectFilter::make('order_status')
                    ->label('Order Status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'packed' => 'Packed',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('shipping_status')
                    ->label('Shipping Status')
                    ->options([
                        'not_shipped' => 'Not Shipped',
                        'packed' => 'Packed',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'returned' => 'Returned',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}