<?php

namespace App\Filament\Resources;

use App\Domain\Intelligence\Models\IntelligenceImport;
use App\Filament\Resources\IntelligenceImportResource\Pages;
use Filament\Tables;
use Filament\Tables\Table;

/** Intelligence › Importações: histórico de envios da empresa. Só nasce pelo importador (nada é criado/editado à mão). */
class IntelligenceImportResource extends BaseResource
{
    protected static ?string $model = IntelligenceImport::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Importações';

    protected static ?string $navigationGroup = 'Intelligence';

    protected static ?int $navigationSort = 8;

    protected static ?string $modelLabel = 'Importação';

    protected static ?string $pluralModelLabel = 'Importações';

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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('snapshot.data_referencia')->label('Referência')->date('d/m/Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->label('Enviada em')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('arquivo')->label('Arquivo')->limit(30)->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn ($state) => ['pendente' => 'Pendente', 'processando' => 'Processando', 'concluida' => 'Concluída', 'falhou' => 'Falhou'][$state] ?? $state)
                    ->color(fn ($state) => ['concluida' => 'success', 'falhou' => 'danger'][$state] ?? 'gray'),
                Tables\Columns\TextColumn::make('indice_geral')->label('Índice geral')
                    ->state(fn (IntelligenceImport $r) => $r->snapshot?->diagnostics->firstWhere('area', 'geral'))
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state->pontuacao, 0).' · '.$state->faixa : '—'),
                Tables\Columns\TextColumn::make('user.name')->label('Enviada por')->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('ver')->label('Ver resultado')->icon('heroicon-o-eye')
                    ->visible(fn (IntelligenceImport $r) => $r->snapshot !== null)
                    ->modalHeading(fn (IntelligenceImport $r) => 'Snapshot de '.$r->snapshot?->data_referencia?->format('d/m/Y'))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (IntelligenceImport $r) => view('filament.intelligence.resultado', ['snapshot' => $r->snapshot])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListIntelligenceImports::route('/')];
    }
}
