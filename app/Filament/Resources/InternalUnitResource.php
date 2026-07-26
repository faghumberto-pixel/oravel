<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InternalUnitResource\Pages;
use App\Models\InternalUnit;
use App\Services\CepGeocodingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * Registro de sede/filiais proprias do tenant -- vira relevante a partir
 * de Contract.internal_unit_id (local_tipo=sede_empresa): o contrato de
 * locacao aponta pra uma unidade daqui em vez de duplicar endereco. Por
 * isso navegacao volta a aparecer (estava com shouldRegisterNavigation=false,
 * inacessivel pela UI antes desta mudanca).
 */
class InternalUnitResource extends Resource
{
    protected static ?string $model = InternalUnit::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Unidades Internas';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', Auth::user()->tenant_id);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação da Unidade')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome da Unidade/Filial')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('code')
                        ->label('Código')
                        ->placeholder('MATRIZ, FILIAL-01...')
                        ->maxLength(255),
                    Forms\Components\Select::make('type')
                        ->label('Tipo')
                        ->options(['matriz' => 'Matriz', 'filial' => 'Filial', 'deposito' => 'Depósito'])
                        ->default('filial')
                        ->native(false)
                        ->required(),
                    Forms\Components\Select::make('responsible_user_id')
                        ->label('Responsável')
                        ->relationship('responsibleUser', 'name', fn (Builder $query) => $query->where('tenant_id', Auth::user()->tenant_id))
                        ->searchable()
                        ->preload(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Ativa')
                        ->default(true),
                ]),

            Forms\Components\Section::make('Endereço')
                ->description('Preencha o CEP: endereço e localização no mapa são preenchidos automaticamente (mesmo padrão do Mapa de Leads).')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('cep')
                        ->label('CEP')
                        ->placeholder('00000-000')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                            if (! $state) {
                                return;
                            }

                            $service = app(CepGeocodingService::class);
                            $endereco = $service->lookupCep($state);

                            if (! $endereco) {
                                Notification::make()->title('CEP não encontrado.')->warning()->send();

                                return;
                            }

                            $set('address', $endereco['address']);
                            $set('city', $endereco['city']);
                            $set('state', $endereco['uf']);

                            $fullAddress = trim($endereco['address'].', '.$endereco['city'].' - '.$endereco['uf']);
                            $coords = $service->geocodeAddress($fullAddress);

                            if ($coords) {
                                $set('latitude', $coords['latitude']);
                                $set('longitude', $coords['longitude']);
                                Notification::make()->title('Endereço localizado no mapa.')->success()->send();
                            } else {
                                $set('latitude', null);
                                $set('longitude', null);
                                Notification::make()->title('Endereço preenchido, mas não foi possível localizar no mapa automaticamente.')->warning()->send();
                            }
                        }),
                    Forms\Components\TextInput::make('city')->label('Cidade'),
                    Forms\Components\TextInput::make('state')->label('UF')->maxLength(2),
                    Forms\Components\TextInput::make('address')->label('Endereço')->columnSpanFull(),
                    Forms\Components\Placeholder::make('geo_status')
                        ->label('Localização no Mapa')
                        ->columnSpanFull()
                        ->content(fn (Forms\Get $get) => $get('latitude') && $get('longitude')
                            ? new HtmlString('<span class="text-emerald-500 font-semibold">✓ Localizado no mapa</span>')
                            : new HtmlString('<span class="text-gray-400">Ainda não localizado — preencha o CEP acima.</span>')),
                    Forms\Components\Hidden::make('latitude'),
                    Forms\Components\Hidden::make('longitude'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'matriz' => 'Matriz',
                        'deposito' => 'Depósito',
                        default => 'Filial',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativa')
                    ->boolean(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Cidade/UF')
                    ->getStateUsing(fn ($record) => $record->city.'/'.$record->state),
                Tables\Columns\TextColumn::make('address')
                    ->label('Endereço')
                    ->limit(30),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInternalUnits::route('/'),
            'create' => Pages\CreateInternalUnit::route('/create'),
            'edit' => Pages\EditInternalUnit::route('/{record}/edit'),
        ];
    }
}
