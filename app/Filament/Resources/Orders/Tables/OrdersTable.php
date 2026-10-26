<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\Actions\ProcessShipmentAction;
use App\Filament\Resources\Orders\Actions\RetryRefundAction;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentSettlementService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                        'refund_required', 'failed', 'expired' => 'danger',
                        'refunded' => 'gray',
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

                TextColumn::make('shipment.biteship_status')
                    ->label('Biteship')
                    ->badge()
                    ->placeholder('-')
                    ->formatStateUsing(fn (?string $state): string => $state ? str_replace('_', ' ', strtoupper($state)) : '-')
                    ->color(fn (?string $state): string => match ($state) {
                        'delivered' => 'success',
                        'cancelled', 'rejected', 'disposed', 'courier_not_found', 'on_hold', 'returned', 'return_in_transit' => 'danger',
                        null => 'gray',
                        default => 'info',
                    }),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('needs_refund')
                    ->label('Needs refund')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                        $query->where('payment_status', 'refund_required')
                            ->orWhereHas('payments', fn (Builder $payments) => $payments->where('status', Payment::STATUS_REFUND_REQUIRED));
                    })),

                SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'pending' => 'Pending',
                        'waiting_confirmation' => 'Waiting Confirmation',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'expired' => 'Expired',
                        'refund_required' => 'Refund Required',
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

                ProcessShipmentAction::make(),
                RetryRefundAction::make(),
                Action::make('markRefunded')
                    ->label('Mark as refunded')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Mark refund as completed?')
                    ->modalDescription('Only confirm after the money has really been returned to the customer, for example by manual bank transfer for a Virtual Account payment.')
                    ->modalSubmitActionLabel('Yes, money returned')
                    ->visible(fn (Order $record): bool => $record->payment_status === 'refund_required'
                        || $record->payments()->where('status', Payment::STATUS_REFUND_REQUIRED)->exists())
                    ->action(function (Order $record): void {
                        app(PaymentSettlementService::class)->markRefundedManually($record);

                        Notification::make()
                            ->title('Refund marked as completed')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}