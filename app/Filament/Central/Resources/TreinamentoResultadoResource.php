<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\TreinamentoResultadoResource\Pages;
use App\Models\TreinamentoResultado;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Resultados do teste público de treinamento (/treinamento/...), somente
 * leitura, só para o super admin da Oravel.
 */
class TreinamentoResultadoResource extends Resource
{
    protected static ?string $model = TreinamentoResultado::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $navigationLabel = 'Resultados de Treinamento';

    protected static ?string $modelLabel = 'Resultado de Treinamento';

    protected static ?string $pluralModelLabel = 'Resultados de Treinamento';

    protected static ?int $navigationSort = 90;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->timezone('America/Sao_Paulo')
                    ->sortable(),
                Tables\Columns\TextColumn::make('nome')->label('Nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('funcao')->label('Função')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('empresa')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->description(fn (TreinamentoResultado $record) => $record->tenant ? 'Cliente: '.$record->tenant->name : null)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('nota')
                    ->label('Nota')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1, ',', ''))
                    ->sortable(),
                Tables\Columns\TextColumn::make('acertos')
                    ->label('Acertos')
                    ->formatStateUsing(fn ($state, TreinamentoResultado $record) => "{$state} de {$record->total}"),
                Tables\Columns\TextColumn::make('aprovado')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Aprovado' : 'Precisa refazer')
                    ->color(fn ($state) => $state ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('treinamento')->label('Treinamento')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('treinamento')
                    ->label('Treinamento')
                    ->options(fn () => TreinamentoResultado::query()->distinct()->orderBy('treinamento')->pluck('treinamento', 'treinamento')->all()),
                Tables\Filters\TernaryFilter::make('aprovado')
                    ->label('Situação')
                    ->trueLabel('Aprovados')
                    ->falseLabel('Precisa refazer')
                    ->native(false),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTreinamentoResultados::route('/'),
        ];
    }
}
