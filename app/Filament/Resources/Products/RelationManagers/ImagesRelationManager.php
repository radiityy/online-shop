<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Product Images';

    protected static ?string $modelLabel = 'Product Image';

    protected static ?string $pluralModelLabel = 'Product Images';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('image_path')
                    ->label('Image')
                    ->image()
                    ->disk('public')
                    ->directory('products')
                    ->visibility('public')
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->default(0),

                Toggle::make('is_primary')
                    ->label('Primary Image')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('image_path')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk('public')
                    ->square(),

                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Sort')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Image')
                    ->after(function ($record): void {
                        if ($record->is_primary) {
                            $record->product
                                ->images()
                                ->where('id', '!=', $record->id)
                                ->update(['is_primary' => false]);
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(function ($record): void {
                        if ($record->is_primary) {
                            $record->product
                                ->images()
                                ->where('id', '!=', $record->id)
                                ->update(['is_primary' => false]);
                        }
                    }),

                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}