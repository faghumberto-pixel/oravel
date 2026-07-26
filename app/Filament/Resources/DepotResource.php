<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepotResource\Pages;
use App\Models\Depot;
use App\Services\CepGeocodingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class DepotResource extends BaseResource
{
    protected static ?string $model = Depot::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationLabel = 'Pátios/Depósitos';

    protected static ?string $modelLabel = 'Pátio/Depósito';

    protected static ?string $pluralModelLabel = 'Pátios/Depósitos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('internal_unit_notice')
                ->label('')
                ->columnSpanFull()
                ->visible(fn (?Depot $record) => $record?->internal_unit_id !== null)
                ->content(fn (?Depot $record) => new HtmlString(
                    '<span class="text-sm text-gray-500">Gerado automaticamente a partir da unidade interna <strong>'.e($record?->internalUnit?->name).'</strong> — editar o endereço aqui não altera o cadastro da unidade (Ativos e Materiais → Unidades Internas).</span>'
                )),

            Forms\Components\TextInput::make('name')
                ->label('Nome')
                ->placeholder('Ex: Matriz, Filial SP...')
                ->required()
                ->maxLength(255),

            Forms\Components\Toggle::make('is_default')
                ->label('Pátio padrão')
                ->helperText('Usado como origem quando a mobilização não indica um pátio específico. Só pode haver 1 padrão.'),

            Forms\Components\Section::make('Endereço')
                ->description('Usado para calcular distância/rota até os clientes (App\Services\RouteOptimizationService).')
                ->schema([
                    Forms\Components\TextInput::make('address')->label('Logradouro e Nº')->maxLength(255),
                    Forms\Components\TextInput::make('city')->label('Cidade')->maxLength(100),
                    Forms\Components\TextInput::make('state')->label('UF')->maxLength(2),
                    Forms\Components\TextInput::make('zip_code')
                        ->label('CEP')
                        ->maxLength(15)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                            if (! $state) {
                                return;
                            }

                            $cepResult = app(CepGeocodingService::class)->lookupCep($state);

                            if ($cepResult) {
                                $set('address', $cepResult['address']);
                                $set('city', $cepResult['city']);
                                $set('state', $cepResult['uf']);
                            }

                            $fullAddress = trim(implode(', ', array_filter([
                                $get('address'),
                                $get('city'),
                                $get('state'),
                            ])));

                            if (! $fullAddress) {
                                return;
                            }

                            $coords = app(CepGeocodingService::class)->geocodeAddress($fullAddress);

                            if ($coords) {
                                $set('latitude', $coords['latitude']);
                                $set('longitude', $coords['longitude']);
                                Notification::make()->title('Pátio localizado no mapa.')->success()->send();
                            } else {
                                $set('latitude', null);
                                $set('longitude', null);
                                Notification::make()->title('CEP preenchido, mas não foi possível localizar automaticamente.')->warning()->send();
                            }
                        }),
                    Forms\Components\Hidden::make('latitude'),
                    Forms\Components\Hidden::make('longitude'),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->weight('bold')->searchable(),
                Tables\Columns\TextColumn::make('internalUnit.name')
                    ->label('Unidade Vinculada')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Avulso'),
                Tables\Columns\TextColumn::make('city')->label('Cidade'),
                Tables\Columns\TextColumn::make('state')->label('UF'),
                Tables\Columns\IconColumn::make('is_default')->label('Padrão')->boolean(),
                Tables\Columns\IconColumn::make('latitude')
                    ->label('Geolocalizado')
                    ->boolean()
                    ->getStateUsing(fn (Depot $record) => $record->hasCoordinates()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepots::route('/'),
            'create' => Pages\CreateDepot::route('/create'),
            'edit' => Pages\EditDepot::route('/{record}/edit'),
        ];
    }
}
