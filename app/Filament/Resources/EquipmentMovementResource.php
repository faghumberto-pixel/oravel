<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSuperAdminTenantColumn;
use App\Filament\Resources\EquipmentMovementResource\Pages;
use App\Models\EquipmentMovement;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Historico/listagem de Mobilizacao e Desmobilizacao -- ate aqui esses
 * registros so apareciam espalhados no Kanban ou no dossie do ativo, sem
 * nenhuma tela pra ver todo mundo com filtro por periodo. So-leitura de
 * proposito: EquipmentMovement nasce pelo fluxo mobile (EquipmentMovementMobile/
 * RentalDispatchChecklistMobile) ou pelo agendamento da Programacao
 * (AgendaTecnicoWidget) -- nao faz sentido um formulario de criacao aqui.
 */
class EquipmentMovementResource extends Resource
{
    use HasSuperAdminTenantColumn;

    protected static ?string $model = EquipmentMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationLabel = 'Histórico de Movimentações';

    protected static ?string $modelLabel = 'Movimentação';

    protected static ?string $pluralModelLabel = 'Movimentações';

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * So pra alimentar a modal do ViewAction (registro nasce pelo fluxo
     * mobile/Programacao, nunca por aqui) -- campos desabilitados, mesmo
     * padrao ja usado em TimeEntriesRelationManager pra exibicao so-leitura.
     */
    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->options([
                    EquipmentMovement::TYPE_MOBILIZACAO => 'Mobilização',
                    EquipmentMovement::TYPE_DESMOBILIZACAO => 'Desmobilização',
                ])
                ->disabled(),
            Forms\Components\Select::make('status')
                ->label('Status')
                ->options([
                    EquipmentMovement::STATUS_AGUARDANDO_VISTORIA => 'Aguardando Vistoria',
                    EquipmentMovement::STATUS_EM_ANDAMENTO => 'Em Andamento',
                    EquipmentMovement::STATUS_AGUARDANDO_APROVACAO => 'Aguardando Aprovação',
                    EquipmentMovement::STATUS_CONCLUIDO => 'Concluído',
                ])
                ->disabled(),
            Forms\Components\Select::make('asset_id')
                ->label('Ativo')
                ->relationship('asset', 'name')
                ->disabled(),
            Forms\Components\Select::make('fleet_vehicle_id')
                ->label('Veículo')
                ->relationship('fleetVehicle', 'placa')
                ->disabled(),
            Forms\Components\Select::make('fleet_driver_id')
                ->label('Motorista')
                ->relationship('fleetDriver', 'name')
                ->disabled(),
            Forms\Components\TextInput::make('km_inicial')->label('KM Inicial')->disabled(),
            Forms\Components\TextInput::make('km_final')->label('KM Final')->disabled(),
            Forms\Components\TextInput::make('custo_transporte')->label('Custo do Transporte (R$)')->prefix('R$')->disabled(),
            Forms\Components\DateTimePicker::make('scheduled_at')->label('Programado Para')->disabled(),
            Forms\Components\DateTimePicker::make('started_at')->label('Iniciado')->disabled(),
            Forms\Components\DateTimePicker::make('completed_at')->label('Concluído')->disabled(),
            Forms\Components\Textarea::make('notes')->label('Observações')->disabled()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::tenantColumn(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        EquipmentMovement::TYPE_MOBILIZACAO => 'Mobilização',
                        EquipmentMovement::TYPE_DESMOBILIZACAO => 'Desmobilização',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        EquipmentMovement::TYPE_MOBILIZACAO => 'success',
                        EquipmentMovement::TYPE_DESMOBILIZACAO => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('asset.patrimonio')
                    ->label('Patrimônio')
                    ->searchable(),
                Tables\Columns\TextColumn::make('asset.name')
                    ->label('Ativo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('fleetVehicle.placa')
                    ->label('Veículo')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('fleetDriver.name')
                    ->label('Motorista')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('km_percorrido')
                    ->label('KM Rodado')
                    ->placeholder('—')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state, 1, ',', '.').' km' : '—'),
                Tables\Columns\TextColumn::make('custo_transporte')
                    ->label('Custo Transporte')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        EquipmentMovement::STATUS_AGUARDANDO_VISTORIA => 'Aguardando Vistoria',
                        EquipmentMovement::STATUS_EM_ANDAMENTO => 'Em Andamento',
                        EquipmentMovement::STATUS_AGUARDANDO_APROVACAO => 'Aguardando Aprovação',
                        EquipmentMovement::STATUS_CONCLUIDO => 'Concluído',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        EquipmentMovement::STATUS_AGUARDANDO_VISTORIA => 'gray',
                        EquipmentMovement::STATUS_EM_ANDAMENTO => 'warning',
                        EquipmentMovement::STATUS_AGUARDANDO_APROVACAO => 'danger',
                        EquipmentMovement::STATUS_CONCLUIDO => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Programado Para')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Iniciado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Concluído')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        EquipmentMovement::TYPE_MOBILIZACAO => 'Mobilização',
                        EquipmentMovement::TYPE_DESMOBILIZACAO => 'Desmobilização',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        EquipmentMovement::STATUS_AGUARDANDO_VISTORIA => 'Aguardando Vistoria',
                        EquipmentMovement::STATUS_EM_ANDAMENTO => 'Em Andamento',
                        EquipmentMovement::STATUS_AGUARDANDO_APROVACAO => 'Aguardando Aprovação',
                        EquipmentMovement::STATUS_CONCLUIDO => 'Concluído',
                    ]),
                Tables\Filters\SelectFilter::make('asset_id')
                    ->label('Ativo')
                    ->relationship('asset', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('fleet_driver_id')
                    ->label('Motorista')
                    ->relationship('fleetDriver', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('periodo')
                    ->form([
                        DatePicker::make('de')->label('De'),
                        DatePicker::make('ate')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['de'], fn (Builder $q, $date) => $q->whereDate('scheduled_at', '>=', $date))
                            ->when($data['ate'], fn (Builder $q, $date) => $q->whereDate('scheduled_at', '<=', $date));
                    }),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipmentMovements::route('/'),
            'view' => Pages\ViewEquipmentMovement::route('/{record}'),
        ];
    }
}
