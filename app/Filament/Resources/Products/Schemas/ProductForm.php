<?php

namespace App\Filament\Resources\Products\Schemas;

use Closure;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Information')
                    ->description('Produk mewakili satu artikel / satu warna. Varian hanya digunakan untuk size dan stock.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Product Name')
                                    ->placeholder('NEVERENDING Oversized Tee Black')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->placeholder('neverending-oversized-tee-black')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->helperText('Gunakan huruf kecil dan tanda minus. Contoh: oversized-tee-black'),

                                Select::make('category_id')
                                    ->label('Category')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                TextInput::make('price')
                                    ->label('Price')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('Rp'),

                                TextInput::make('sale_price')
                                    ->label('Sale Price')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('Rp')
                                    ->helperText('Kosongkan jika produk tidak sedang diskon. Sale price harus lebih kecil dari price.')
                                    ->rules([
                                        'nullable',
                                        'numeric',
                                        function (Get $get): Closure {
                                            return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                                if ($value === null || $value === '') {
                                                    return;
                                                }

                                                $price = (float) ($get('price') ?? 0);
                                                $salePrice = (float) $value;

                                                if ($price > 0 && $salePrice >= $price) {
                                                    $fail('Sale price harus lebih kecil dari price.');
                                                }
                                            };
                                        },
                                    ]),

                                TextInput::make('weight')
                                    ->label('Weight')
                                    ->required()
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->suffix('gram')
                                    ->helperText('Berat produk dalam gram untuk kebutuhan pengiriman.'),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->required()
                                    ->columnSpanFull(),

                                RichEditor::make('description')
                                    ->label('Description')
                                    ->placeholder('Deskripsi produk, bahan, cutting, dan detail NEVERENDING.')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}