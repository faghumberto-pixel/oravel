<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FleetStatusResource\Pages;
use App\Models\FleetStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Filament\Facades\Filament;

class FleetStatusResource extends Resource
{
    protected static ?string $model = FleetStatus::class;
    protected static ?string $tenantOwnershipRelationshipName = 'tenant';
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Disponibilidade de equipamentos';
    protected static ?string $navigationGroup = 'GESTÃO COMERCIAL';
    protected static ?int $navigationSort = 5;

    /**
     * Bloqueia a criação manual de registros de frota
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Status do Ativo')->schema([
                    Forms\Components\Select::make('asset_id')
                        ->relationship('asset', 'name')
                        ->label('Equipamento')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->name ?? "Patrimônio: {$record->patrimonio}")
                        ->disabled(),
                    Forms\Components\Toggle::make('is_available')
                        ->label('Disponível para Locação')
                        ->onColor('success')
                        ->offColor('danger'),
                    Forms\Components\TextInput::make('capacity_label')
                        ->label('Capacidade/Modelo')
                        ->required(),
                    Forms\Components\Select::make('last_maintenance_id')
                        ->relationship('maintenanceOrder', 'os_number')
                        ->label('Última Manutenção')
                        ->disabled(),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Ativo')
                    ->description(fn($record) => "Pat: {$record->asset?->patrimonio}")
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('capacity_label')
                    ->label('Capacidade')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\IconColumn::make('is_available')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Atualização')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_available')->label('Apenas Disponíveis'),
                Tables\Filters\SelectFilter::make('capacity_label')
                    ->label('Capacidade')
                    ->options(fn() => DB::table('fleet_statuses')
                        ->where('tenant_id', Filament::getTenant()?->id)
                        ->whereNotNull('capacity_label')
                        ->distinct()
                        ->pluck('capacity_label', 'capacity_label')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFleetStatuses::route('/'),
            'edit' => Pages\EditFleetStatus::route('/{record}/edit'),
        ];
    }
}