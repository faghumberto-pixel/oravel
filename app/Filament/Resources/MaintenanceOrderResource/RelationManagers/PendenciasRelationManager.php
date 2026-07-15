<?php

namespace App\Filament\Resources\MaintenanceOrderResource\RelationManagers;

use App\Models\MaintenanceOrderPendencia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Cria Pendencias a partir da OS -- notificacao pros 3 papeis de
 * manutencao acontece em MaintenanceOrderPendenciaObserver::created().
 * So' Create + View aqui (resolver acontece em
 * App\Filament\Pages\EventosEFalhas, nao editando o registro).
 */
class PendenciasRelationManager extends RelationManager
{
    protected static string $relationship = 'pendencias';

    protected static ?string $title = 'Pendências';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Textarea::make('description')
                ->label('Descrição da Pendência')
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Aberta em')->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('description')->label('Descrição')->wrap(),
                Tables\Columns\TextColumn::make('createdBy.name')->label('Aberta por'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => $state === MaintenanceOrderPendencia::STATUS_RESOLVIDA ? 'success' : 'warning')
                    ->formatStateUsing(fn (string $state) => $state === MaintenanceOrderPendencia::STATUS_RESOLVIDA ? 'Resolvida' : 'Aberta'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by_user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }
}
