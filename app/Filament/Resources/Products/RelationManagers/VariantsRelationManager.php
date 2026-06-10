<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Sizes / Stock';

    protected static ?string $modelLabel = 'Size Variant';

    protected static ?string $pluralModelLabel = 'Size Variants';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('size')
                    ->label('Size')
                    ->placeholder('S / M / L / XL / XXL')
                    ->required()
                    ->maxLength(50),

                Hidden::make('color')
                    ->default(null),

                TextInput::make('sku')
                    ->label('SKU')
                    ->placeholder('NE-TEE-BLK-S')
                    ->maxLength(100),

                TextInput::make('stock')
                    ->label('Stock')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->default(0),

                TextInput::make('additional_price')
                    ->label('Additional Price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('Rp'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->required()
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('size')
            ->columns([
                TextColumn::make('size')
                    ->label('Size')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('stock')
                    ->label('Stock')
                    ->sortable(),

                TextColumn::make('additional_price')
                    ->label('Additional Price')
                    ->money('IDR')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Size Variant'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}