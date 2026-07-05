<?php

namespace App\Filament\Resources\FleetVehicleResource\RelationManagers;

use App\Models\FleetMaintenancePlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MaintenancePlansRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenancePlans';

    protected static ?string $title = 'Planos de Manutenção Preventiva';

    public function form(Form $form): Form
    {
        return $form->schema([
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
            Forms\Components\TextInput::make('intervalo_km')
                ->label('Intervalo (KM)')
                ->helperText('Ex: a cada 10.000 km')
                ->numeric(),
            Forms\Components\TextInput::make('intervalo_dias')
                ->label('Intervalo (dias)')
                ->helperText('Ex: a cada 30 dias')
                ->numeric(),
            Forms\Components\TextInput::make('ultima_execucao_km')
                ->label('KM da Última Execução')
                ->numeric(),
            Forms\Components\DatePicker::make('ultima_execucao_data')
                ->label('Data da Última Execução'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tipo_servico')
            ->columns([
                Tables\Columns\TextColumn::make('tipo_servico')
                    ->label('Serviço')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        FleetMaintenancePlan::TIPO_OLEO => 'Troca de Óleo',
                        FleetMaintenancePlan::TIPO_PNEU => 'Troca/Rodízio de Pneus',
                        FleetMaintenancePlan::TIPO_LAVAGEM => 'Lavagem',
                        FleetMaintenancePlan::TIPO_REVISAO => 'Revisão Geral',
                        default => 'Outro',
                    }),
                Tables\Columns\TextColumn::make('intervalo_km')->label('Intervalo KM'),
                Tables\Columns\TextColumn::make('intervalo_dias')->label('Intervalo Dias'),
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
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
