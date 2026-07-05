<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FleetMaintenancePlanResource\Pages;
use App\Models\FleetMaintenancePlan;
use App\Models\FleetVehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FleetMaintenancePlanResource extends Resource
{
    protected static ?string $model = FleetMaintenancePlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationLabel = 'Planos de Manutenção de Veículos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('fleet_vehicle_id')
                ->label('Veículo')
                ->options(fn () => FleetVehicle::pluck('placa', 'id'))
                ->searchable()
                ->required(),
            Forms\Components\Select::make('tipo_servico')
                ->label('Tipo de Serviço')
                ->options([
                    FleetMaintenancePlan::TIPO_OLEO => 'Troca de Óleo',
                    FleetMaintenancePlan::TIPO_PNEU => 'Troca/Rodízio de Pneus',
                    FleetMaintenancePlan::TIPO_LAVAGEM => 'Lavagem',
                    FleetMaintenancePlan::TIPO_REVISAO => 'Revisão Geral',
                    FleetMaintenancePlan::TIPO_OUTRO => 'Outro',
                ])
                ->required()
                ->native(false),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('intervalo_km')
                    ->label('Intervalo (KM)')
                    ->helperText('Ex: a cada 10.000 km')
                    ->numeric(),
                Forms\Components\TextInput::make('intervalo_dias')
                    ->label('Intervalo (dias)')
                    ->helperText('Ex: a cada 30 dias')
                    ->numeric(),
            ]),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('ultima_execucao_km')
                    ->label('KM da Última Execução')
                    ->numeric(),
                Forms\Components\DatePicker::make('ultima_execucao_data')
                    ->label('Data da Última Execução'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fleetVehicle.placa')->label('Veículo')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tipo_servico')
                    ->label('Serviço')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        FleetMaintenancePlan::TIPO_OLEO => 'Troca de Óleo',
                        FleetMaintenancePlan::TIPO_PNEU => 'Troca/Rodízio de Pneus',
                        FleetMaintenancePlan::TIPO_LAVAGEM => 'Lavagem',
                        FleetMaintenancePlan::TIPO_REVISAO => 'Revisão Geral',
                        default => 'Outro',
                    }),
                Tables\Columns\TextColumn::make('proxima_execucao_km')->label('Próxima (KM)'),
                Tables\Columns\TextColumn::make('proxima_execucao_data')->label('Próxima (Data)')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('status_preventiva')
                    ->label('Status')
                    ->state(fn (FleetMaintenancePlan $record) => match (true) {
                        $record->isVencido() => 'Vencido',
                        $record->isProximoVencimento() => 'Próximo',
                        default => 'Em dia',
                    })
                    ->badge()
                    ->color(fn (FleetMaintenancePlan $record) => match (true) {
                        $record->isVencido() => 'danger',
                        $record->isProximoVencimento() => 'warning',
                        default => 'success',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_servico')
                    ->options([
                        FleetMaintenancePlan::TIPO_OLEO => 'Troca de Óleo',
                        FleetMaintenancePlan::TIPO_PNEU => 'Troca/Rodízio de Pneus',
                        FleetMaintenancePlan::TIPO_LAVAGEM => 'Lavagem',
                        FleetMaintenancePlan::TIPO_REVISAO => 'Revisão Geral',
                        FleetMaintenancePlan::TIPO_OUTRO => 'Outro',
                    ]),
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
            'index' => Pages\ListFleetMaintenancePlans::route('/'),
            'create' => Pages\CreateFleetMaintenancePlan::route('/create'),
            'edit' => Pages\EditFleetMaintenancePlan::route('/{record}/edit'),
        ];
    }
}
