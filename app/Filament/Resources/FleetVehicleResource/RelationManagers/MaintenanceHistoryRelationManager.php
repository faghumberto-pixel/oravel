<?php

namespace App\Filament\Resources\FleetVehicleResource\RelationManagers;

use App\Models\FleetMaintenancePlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MaintenanceHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceHistory';

    protected static ?string $title = 'Histórico de Manutenção Realizada';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('fleet_maintenance_plan_id')
                ->label('Plano de Preventiva (opcional)')
                ->options(fn () => $this->getOwnerRecord()->maintenancePlans()->get()->mapWithKeys(
                    fn (FleetMaintenancePlan $plan) => [$plan->id => $plan->tipo_servico]
                ))
                ->searchable(),
            Forms\Components\TextInput::make('tipo_servico')
                ->label('Tipo de Serviço')
                ->required(),
            Forms\Components\TextInput::make('km_na_execucao')
                ->label('KM na Execução')
                ->numeric(),
            Forms\Components\DatePicker::make('data_execucao')
                ->label('Data da Execução')
                ->required()
                ->default(now()),
            Forms\Components\TextInput::make('custo')
                ->label('Custo (R$)')
                ->numeric()
                ->prefix('R$'),
            Forms\Components\TextInput::make('fornecedor')
                ->label('Oficina/Fornecedor'),
            Forms\Components\Textarea::make('notes')
                ->label('Observações')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tipo_servico')
            ->columns([
                Tables\Columns\TextColumn::make('tipo_servico')->label('Serviço'),
                Tables\Columns\TextColumn::make('data_execucao')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('km_na_execucao')->label('KM'),
                Tables\Columns\TextColumn::make('custo')->label('Custo')->money('BRL'),
                Tables\Columns\TextColumn::make('fornecedor')->label('Fornecedor'),
            ])
            ->defaultSort('data_execucao', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
