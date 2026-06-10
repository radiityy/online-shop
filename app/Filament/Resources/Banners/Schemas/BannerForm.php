<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Banner Information')
                    ->description('Banner aktif pertama akan tampil sebagai hero utama di storefront.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('title')
                                    ->label('Title')
                                    ->placeholder('New Arrival')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('link_url')
                                    ->label('Link URL')
                                    ->placeholder('/products')
                                    ->maxLength(255)
                                    ->default('/products'),

                                TextInput::make('subtitle')
                                    ->label('Subtitle')
                                    ->placeholder('Daily wear for endless rotation.')
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                FileUpload::make('image_path')
                                    ->label('Banner Image')
                                    ->image()
                                    ->acceptedFileTypes([
                                        'image/jpeg',
                                        'image/png',
                                        'image/webp',
                                    ])
                                    ->maxSize(20096)
                                    ->disk('public')
                                    ->directory('banners')
                                    ->visibility('public')
                                    ->imageEditor()
                                    ->imageResizeMode('cover')
                                    ->imageCropAspectRatio('16:9')
                                    ->imageResizeTargetWidth('1920')
                                    ->imageResizeTargetHeight('1080')
                                    ->required()
                                    ->columnSpanFull()
                                    ->helperText('Gunakan JPG, PNG, atau WEBP. Maksimal 4MB. Rekomendasi rasio 16:9.'),

                                TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->required(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}