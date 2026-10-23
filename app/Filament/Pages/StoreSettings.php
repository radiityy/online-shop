<?php

namespace App\Filament\Pages;

use App\Exceptions\ShippingException;
use App\Models\StoreSetting;
use App\Services\BiteshipService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class StoreSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Store Settings';

    protected static ?string $title = 'Store Settings';

    protected static ?string $slug = 'store-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $setting = StoreSetting::current();

        $this->form->fill([
            'origin_contact_name' => $setting->origin_contact_name,
            'origin_contact_phone' => $setting->origin_contact_phone,
            'origin_area_id' => $setting->origin_area_id,
            'origin_postal_code' => $setting->origin_postal_code,
            'origin_address' => $setting->origin_address,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Shipping origin')
                    ->description('Titik asal paket. Ongkir dihitung dari area ini, dan checkout tidak bisa menampilkan ongkir sebelum area ini diisi.')
                    ->schema([
                        Select::make('origin_area_id')
                            ->label('Origin area')
                            ->placeholder('Ketik minimal 3 huruf nama kecamatan atau kota')
                            ->searchable()
                            ->searchDebounce(700)
                            ->live()
                            ->required()
                            ->getSearchResultsUsing(fn (string $search): array => $this->searchAreas($search))
                            ->getOptionLabelUsing(fn ($value): ?string => $this->areaLabel((string) $value))
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $area = $state ? app(BiteshipService::class)->findKnownArea((string) $state) : null;

                                if ($area && filled($area['postal_code'])) {
                                    $set('origin_postal_code', $area['postal_code']);
                                }
                            }),
                        TextInput::make('origin_postal_code')
                            ->label('Postal code')
                            ->required()
                            ->regex('/^\d{5}$/')
                            ->maxLength(5),
                        TextInput::make('origin_contact_name')
                            ->label('Contact name')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('origin_contact_phone')
                            ->label('Contact phone')
                            ->required()
                            ->tel()
                            ->regex('/^\+?[0-9]{8,15}$/')
                            ->maxLength(20),
                        Textarea::make('origin_address')
                            ->label('Pickup address')
                            ->required()
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())
                            ->alignment(Alignment::Start)
                            ->key('form-actions'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $setting = StoreSetting::current();
        $areaId = (string) $data['origin_area_id'];
        $areaName = $setting->origin_area_name;

        if ($areaId !== (string) $setting->origin_area_id) {
            $area = app(BiteshipService::class)->findKnownArea($areaId);

            if (! $area) {
                throw ValidationException::withMessages([
                    'data.origin_area_id' => 'Area tidak dikenali atau pencarian sudah kedaluwarsa. Cari dan pilih ulang areanya.',
                ]);
            }

            $areaName = $area['name'];
        }

        $setting->fill([
            'origin_contact_name' => $data['origin_contact_name'],
            'origin_contact_phone' => $data['origin_contact_phone'],
            'origin_area_id' => $areaId,
            'origin_area_name' => $areaName,
            'origin_postal_code' => $data['origin_postal_code'],
            'origin_address' => $data['origin_address'],
        ])->save();

        Notification::make()
            ->title('Store settings saved')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->submit('save'),
        ];
    }

    private function searchAreas(string $search): array
    {
        if (mb_strlen(trim($search)) < 3) {
            return [];
        }

        try {
            $areas = app(BiteshipService::class)->searchAreas($search);
        } catch (ShippingException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            return [];
        }

        return collect($areas)
            ->take(10)
            ->mapWithKeys(fn (array $area) => [$area['id'] => $area['name']])
            ->all();
    }

    private function areaLabel(string $areaId): ?string
    {
        if ($areaId === '') {
            return null;
        }

        $setting = StoreSetting::current();

        if ($areaId === (string) $setting->origin_area_id && filled($setting->origin_area_name)) {
            return $setting->origin_area_name;
        }

        return app(BiteshipService::class)->findKnownArea($areaId)['name'] ?? $areaId;
    }
}
