<?php

namespace App\Filament\Central\Resources\TenantResource\RelationManagers;

use App\Models\TenantEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Histórico do cliente: linha do tempo da relação com a Oravel + anotações do operador. */
class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Histórico do cliente';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->label('Assunto')->required()->maxLength(255)
                ->placeholder('Ex.: Conversa por telefone, proposta enviada, treinamento agendado'),
            Forms\Components\Textarea::make('description')->label('Detalhes')->rows(4),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes())
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')->label('Quando')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('event_type')->label('Tipo')->badge()
                    ->formatStateUsing(fn ($state) => TenantEvent::types()[$state]['label'] ?? $state)
                    ->color(fn ($state) => TenantEvent::types()[$state]['color'] ?? 'gray'),
                Tables\Columns\TextColumn::make('title')->label('O que aconteceu')->wrap()->weight('bold'),
                Tables\Columns\TextColumn::make('description')->label('Detalhes')->wrap()->placeholder('—'),
                Tables\Columns\TextColumn::make('actor.name')->label('Registrado por')->placeholder('Sistema'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')->label('Tipo')
                    ->options(collect(TenantEvent::types())->map(fn ($t) => $t['label'])->all()),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Registrar anotação')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Registrar anotação no histórico')
                    ->mutateFormDataUsing(fn (array $data) => $data + [
                        'event_type' => TenantEvent::ANOTACAO,
                        'occurred_at' => now(),
                        'actor_user_id' => auth()->id(),
                    ]),
            ])
            // Histórico é permanente: só anotações podem ser apagadas.
            ->actions([
                Tables\Actions\DeleteAction::make()->visible(fn (TenantEvent $record) => $record->event_type === TenantEvent::ANOTACAO),
            ]);
    }
}
