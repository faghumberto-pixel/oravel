<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FreightRecordResource\Pages;
use App\Models\EquipmentMovement;
use App\Models\FleetVehicle;
use App\Models\FreightCarrier;
use App\Models\FreightRecord;
use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FreightRecordResource extends Resource
{
    protected static ?string $model = FreightRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationLabel = 'Fretes';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('equipment_movement_id')
                ->label('Mobilização / Desmobilização')
                ->searchable()
                ->required()
                ->getSearchResultsUsing(function (string $search) {
                    $tenantId = Tenancy::current()?->id;
                    if (! $tenantId) {
                        return [];
                    }

                    return EquipmentMovement::where('tenant_id', $tenantId)
                        ->whereHas('asset', fn ($q) => $q->where('name', 'ilike', "%{$search}%"))
                        ->orWhereHas('maintenanceOrder', fn ($q) => $q->where('tenant_id', $tenantId)->where('os_number', 'ilike', "%{$search}%"))
                        ->with(['asset', 'maintenanceOrder'])
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (EquipmentMovement $m) => [
                            $m->id => sprintf(
                                '%s - %s (%s)',
                                $m->maintenanceOrder?->os_number ?? '—',
                                $m->asset?->name ?? 'Ativo indisponível',
                                $m->type === EquipmentMovement::TYPE_MOBILIZACAO ? 'Mobilização' : 'Desmobilização'
                            ),
                        ]);
                })
                ->getOptionLabelUsing(function ($value) {
                    $m = EquipmentMovement::withoutGlobalScopes()->with(['asset', 'maintenanceOrder'])->find($value);

                    return $m
                        ? sprintf('%s - %s (%s)', $m->maintenanceOrder?->os_number ?? '—', $m->asset?->name ?? '—', $m->type)
                        : null;
                })
                ->columnSpanFull(),

            Forms\Components\Select::make('tipo')
                ->label('Tipo de Frete')
                ->options([
                    FreightRecord::TIPO_PROPRIO => 'Frota Própria',
                    FreightRecord::TIPO_TERCEIRIZADO => 'Frete Terceirizado',
                ])
                ->required()
                ->live()
                ->native(false),

            Forms\Components\Select::make('fleet_vehicle_id')
                ->label('Veículo')
                ->options(fn () => FleetVehicle::pluck('placa', 'id'))
                ->searchable()
                ->visible(fn (Get $get) => $get('tipo') === FreightRecord::TIPO_PROPRIO)
                ->required(fn (Get $get) => $get('tipo') === FreightRecord::TIPO_PROPRIO),

            Forms\Components\Select::make('freight_carrier_id')
                ->label('Transportadora')
                ->options(fn () => FreightCarrier::pluck('nome', 'id'))
                ->searchable()
                ->visible(fn (Get $get) => $get('tipo') === FreightRecord::TIPO_TERCEIRIZADO)
                ->required(fn (Get $get) => $get('tipo') === FreightRecord::TIPO_TERCEIRIZADO),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('origem')->label('Origem'),
                Forms\Components\TextInput::make('destino')->label('Destino'),
            ]),

            Forms\Components\Grid::make(3)->schema([
                Forms\Components\TextInput::make('valor')
                    ->label('Valor do Frete (R$)')
                    ->numeric()
                    ->prefix('R$')
                    ->required(),
                Forms\Components\TextInput::make('km_percorrido')
                    ->label('KM Percorrido')
                    ->numeric(),
                Forms\Components\DatePicker::make('data')
                    ->label('Data')
                    ->required()
                    ->default(now()),
            ]),

            Forms\Components\Grid::make(2)
                ->visible(fn (Get $get) => $get('tipo') === FreightRecord::TIPO_PROPRIO)
                ->schema([
                    Forms\Components\TextInput::make('horas_motorista')
                        ->label('Horas do Motorista')
                        ->numeric(),
                    Forms\Components\TextInput::make('custo_motorista')
                        ->label('Custo do Motorista (R$)')
                        ->numeric()
                        ->prefix('R$'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('equipmentMovement.maintenanceOrder.os_number')
                    ->label('OS'),
                Tables\Columns\TextColumn::make('equipmentMovement.asset.name')
                    ->label('Ativo'),
                Tables\Columns\TextColumn::make('tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === FreightRecord::TIPO_PROPRIO ? 'Frota Própria' : 'Terceirizado')
                    ->color(fn (string $state) => $state === FreightRecord::TIPO_PROPRIO ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('fleetVehicle.placa')->label('Veículo')->placeholder('—'),
                Tables\Columns\TextColumn::make('freightCarrier.nome')->label('Transportadora')->placeholder('—'),
                Tables\Columns\TextColumn::make('origem')->label('Origem'),
                Tables\Columns\TextColumn::make('destino')->label('Destino'),
                Tables\Columns\TextColumn::make('valor')->label('Frete')->money('BRL'),
                Tables\Columns\TextColumn::make('custo_total')->label('Custo Total')->money('BRL')->weight('bold'),
                Tables\Columns\TextColumn::make('data')->label('Data')->date('d/m/Y')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->options([
                        FreightRecord::TIPO_PROPRIO => 'Frota Própria',
                        FreightRecord::TIPO_TERCEIRIZADO => 'Terceirizado',
                    ]),
            ])
            ->defaultSort('data', 'desc')
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
            'index' => Pages\ListFreightRecords::route('/'),
            'create' => Pages\CreateFreightRecord::route('/create'),
            'edit' => Pages\EditFreightRecord::route('/{record}/edit'),
        ];
    }
}
