<?php

namespace App\Filament\Resources\CrmVisitaResource\RelationManagers;

use App\Models\CrmVisitaDespesa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/** Despesas lançadas nesta visita. */
class DespesasRelationManager extends RelationManager
{
    protected static string $relationship = 'despesas';

    protected static ?string $title = 'Despesas da visita';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('data')->label('Data')->default(now())->required(),
            Forms\Components\Select::make('tipo')->label('Tipo')->options(CrmVisitaDespesa::tipoLabels())->default('combustivel')->required(),
            Forms\Components\TextInput::make('valor')->label('Valor (R$)')->numeric()->prefix('R$')->minValue(0.01)->required(),
            Forms\Components\TextInput::make('descricao')->label('Descrição')->maxLength(255),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('descricao')
            ->columns([
                Tables\Columns\TextColumn::make('data')->label('Data')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->formatStateUsing(fn ($state) => CrmVisitaDespesa::tipoLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('valor')->label('Valor')->money('BRL')->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')->label('Total')),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn ($state) => CrmVisitaDespesa::statusLabels()[$state] ?? $state),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Lançar despesa')->mutateFormDataUsing(function (array $data) {
                    $visita = $this->getOwnerRecord();

                    return $data + ['tenant_id' => $visita->tenant_id, 'user_id' => auth()->id(), 'crm_veiculo_id' => $visita->crm_veiculo_id];
                }),
            ])
            ->actions([Tables\Actions\EditAction::make()]);
    }
}
