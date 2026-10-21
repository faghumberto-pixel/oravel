<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FleetVehicleResource\Pages;
use App\Filament\Resources\FleetVehicleResource\RelationManagers;
use App\Models\EquipmentMovement;
use App\Models\FleetVehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FleetVehicleResource extends Resource
{
    protected static ?string $model = FleetVehicle::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationParentItem = 'Frota';

    protected static ?string $navigationLabel = 'Veículos da Frota';

    protected static ?string $modelLabel = 'Veículo da Frota';

    protected static ?string $pluralModelLabel = 'Veículos da Frota';

    protected static ?int $navigationSort = 4;

    /** Interface simples (padrão de quem não contratou Locadora; a Central pode forçar por empresa). */
    public static function interfaceSimples(): bool
    {
        return \App\Support\Tenancy::current()?->interfaceSimples() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dados do Veículo')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('placa')
                        ->label('Placa')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(20),
                    Forms\Components\TextInput::make('modelo')
                        ->label('Modelo')
                        ->required(),
                    Forms\Components\Select::make('tipo')
                        ->label('Tipo')
                        ->options([
                            'carreta' => 'Carreta',
                            'caminhao' => 'Caminhão',
                            'guincho' => 'Guincho',
                            'outro' => 'Outro',
                        ])
                        ->required()
                        ->native(false),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            FleetVehicle::STATUS_DISPONIVEL => 'Disponível',
                            FleetVehicle::STATUS_EM_ROTA => 'Em Rota',
                            FleetVehicle::STATUS_MANUTENCAO => 'Em Manutenção',
                            FleetVehicle::STATUS_INATIVO => 'Inativo',
                        ])
                        ->default(FleetVehicle::STATUS_DISPONIVEL)
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('capacidade_carga')->hidden(fn () => static::interfaceSimples())
                        ->label('Capacidade de Carga (kg)')
                        ->numeric(),
                    Forms\Components\TextInput::make('km_atual')
                        ->label('KM Atual')
                        ->numeric()
                        ->required(),
                    Forms\Components\TextInput::make('custo_por_km')->hidden(fn () => static::interfaceSimples())
                        ->label('Custo por KM (R$)')
                        ->helperText('Usado pra calcular o custo do frete automaticamente ao finalizar uma movimentação. Deixe em branco pro financeiro completar depois.')
                        ->numeric()
                        ->prefix('R$'),
                    Forms\Components\TextInput::make('tag_sem_parar')->hidden(fn () => static::interfaceSimples())
                        ->label('Tag Sem Parar')
                        ->maxLength(50),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('placa')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('modelo')->searchable(),
                Tables\Columns\TextColumn::make('tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'carreta' => 'Carreta',
                        'caminhao' => 'Caminhão',
                        'guincho' => 'Guincho',
                        default => 'Outro',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        FleetVehicle::STATUS_DISPONIVEL => 'success',
                        FleetVehicle::STATUS_EM_ROTA => 'info',
                        FleetVehicle::STATUS_MANUTENCAO => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        FleetVehicle::STATUS_DISPONIVEL => 'Disponível',
                        FleetVehicle::STATUS_EM_ROTA => 'Em Rota',
                        FleetVehicle::STATUS_MANUTENCAO => 'Em Manutenção',
                        default => 'Inativo',
                    }),
                Tables\Columns\TextColumn::make('movimentacao_atual')->hidden(fn () => static::interfaceSimples())
                    ->label('Movimentação Atual')
                    ->state(function (FleetVehicle $record) {
                        if ($record->status !== FleetVehicle::STATUS_EM_ROTA) {
                            return null;
                        }

                        $movement = $record->equipmentMovements()
                            ->where('status', '!=', EquipmentMovement::STATUS_CONCLUIDO)
                            ->latest('started_at')
                            ->first();

                        if (! $movement) {
                            return null;
                        }

                        $tipo = $movement->type === EquipmentMovement::TYPE_MOBILIZACAO ? 'Mobilização' : 'Desmobilização';

                        return "{$tipo} · {$movement->asset?->name}";
                    })
                    ->url(function (FleetVehicle $record) {
                        if ($record->status !== FleetVehicle::STATUS_EM_ROTA) {
                            return null;
                        }

                        $movement = $record->equipmentMovements()
                            ->where('status', '!=', EquipmentMovement::STATUS_CONCLUIDO)
                            ->latest('started_at')
                            ->first();

                        return $movement?->asset ? AssetResource::getUrl('edit', ['record' => $movement->asset]) : null;
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('km_atual')->label('KM Atual')->numeric(decimalPlaces: 0)->sortable(),
                Tables\Columns\TextColumn::make('alertas')->hidden(fn () => static::interfaceSimples())
                    ->label('Alertas')
                    ->state(function (FleetVehicle $record) {
                        $docsVencidos = $record->documents()->get()->filter(fn ($d) => $d->isVencido())->count();
                        $docsProximos = $record->documents()->get()->filter(fn ($d) => $d->isProximoVencimento())->count();
                        $planosVencidos = $record->maintenancePlans()->get()->filter(fn ($p) => $p->isVencido())->count();

                        $partes = [];
                        if ($docsVencidos) {
                            $partes[] = "{$docsVencidos} doc. vencido(s)";
                        }
                        if ($docsProximos) {
                            $partes[] = "{$docsProximos} doc. vencendo";
                        }
                        if ($planosVencidos) {
                            $partes[] = "{$planosVencidos} preventiva(s) vencida(s)";
                        }

                        return $partes ? implode(' · ', $partes) : 'Em dia';
                    })
                    ->badge()
                    ->color(function (FleetVehicle $record) {
                        $vencidos = $record->documents()->get()->filter(fn ($d) => $d->isVencido())->count()
                            + $record->maintenancePlans()->get()->filter(fn ($p) => $p->isVencido())->count();

                        if ($vencidos > 0) {
                            return 'danger';
                        }

                        $proximos = $record->documents()->get()->filter(fn ($d) => $d->isProximoVencimento())->count();

                        return $proximos > 0 ? 'warning' : 'success';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        FleetVehicle::STATUS_DISPONIVEL => 'Disponível',
                        FleetVehicle::STATUS_EM_ROTA => 'Em Rota',
                        FleetVehicle::STATUS_MANUTENCAO => 'Em Manutenção',
                        FleetVehicle::STATUS_INATIVO => 'Inativo',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->visible(fn () => ! static::interfaceSimples()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\MaintenancePlansRelationManager::class,
            RelationManagers\MaintenanceHistoryRelationManager::class,
            RelationManagers\TollRecordsRelationManager::class,
            RelationManagers\FuelRecordsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFleetVehicles::route('/'),
            'create' => Pages\CreateFleetVehicle::route('/create'),
            'edit' => Pages\EditFleetVehicle::route('/{record}/edit'),
        ];
    }
}
