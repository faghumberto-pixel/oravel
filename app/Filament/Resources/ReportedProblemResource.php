<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportedProblemResource\Pages;
use App\Models\ReportedProblem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

/** Problemas comuns: lista que a empresa monta para escolher ao abrir uma OS. Adiciona, edita e desativa; não exclui (as OS antigas apontam para eles). */
class ReportedProblemResource extends BaseResource
{
    protected static ?string $model = ReportedProblem::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationLabel = 'Problemas comuns';

    protected static ?string $modelLabel = 'Problema comum';

    protected static ?string $pluralModelLabel = 'Problemas comuns';

    protected static ?string $slug = 'problemas-comuns';

    protected static ?int $navigationSort = 31;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('description')->label('Problema')->placeholder('Ex.: Não liga, Barulho estranho, Vazamento')->required()->maxLength(160),
            Forms\Components\Toggle::make('is_active')->label('Ativo')->default(true)->helperText('Desative em vez de excluir: as OS antigas continuam mostrando o problema.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description')->label('Problema')->searchable()->sortable()->weight('bold'),
                Tables\Columns\IconColumn::make('is_active')->label('Ativo')->boolean(),
                Tables\Columns\TextColumn::make('maintenance_orders_count')->label('Usado em')->counts('maintenanceOrders')->suffix(' OS'),
            ])
            ->defaultSort('description')
            ->filters([Tables\Filters\TernaryFilter::make('is_active')->label('Situação')->trueLabel('Ativos')->falseLabel('Desativados')->default(true)])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('alternar')
                    ->label(fn (ReportedProblem $r) => $r->is_active ? 'Desativar' : 'Reativar')
                    ->icon(fn (ReportedProblem $r) => $r->is_active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color('gray')
                    ->action(fn (ReportedProblem $r) => $r->update(['is_active' => ! $r->is_active])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReportedProblems::route('/'),
            'create' => Pages\CreateReportedProblem::route('/create'),
            'edit' => Pages\EditReportedProblem::route('/{record}/edit'),
        ];
    }
}
