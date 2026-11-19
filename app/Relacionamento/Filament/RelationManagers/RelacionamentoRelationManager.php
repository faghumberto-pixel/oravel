<?php

namespace App\Relacionamento\Filament\RelationManagers;

use App\Relacionamento\Models\RelInteracao;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

/**
 * Aba "Relacionamento" da ficha da empresa (Prospect e SalesLead): linha do tempo cronologica de tudo
 * que a empresa fez no ecossistema Oravel (oravel.org, WhatsApp, contatos comerciais). Somente leitura:
 * o registro nasce do endpoint/backfill, nunca digitado aqui.
 */
class RelacionamentoRelationManager extends RelationManager
{
    protected static string $relationship = 'relInteracoes';

    protected static ?string $title = 'Relacionamento';

    protected static ?string $icon = 'heroicon-o-clock';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('titulo')
            ->heading('Linha do tempo')
            ->description(function (): string {
                $q = $this->getOwnerRecord()->relInteracoes();
                $total = (clone $q)->count();
                $ultima = (clone $q)->max('ocorrido_em');

                return $total === 0
                    ? 'Nenhuma interação registrada ainda.'
                    : $total.' interações · última em '.Carbon::parse($ultima)->format('d/m/Y H:i');
            })
            ->columns([
                Tables\Columns\TextColumn::make('ocorrido_em')->label('Quando')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('tipo')->label('O que fez')->badge()
                    ->formatStateUsing(fn (string $state): string => RelInteracao::rotulo($state))
                    ->color(fn (string $state): string => RelInteracao::cor($state))
                    ->icon(fn (string $state): string => RelInteracao::icone($state)),
                Tables\Columns\TextColumn::make('titulo')->label('Conteúdo')->weight('bold')->wrap()->placeholder('—'),
                Tables\Columns\TextColumn::make('resultado')->label('Resultado')->wrap()->limit(120)->placeholder('—'),
                Tables\Columns\TextColumn::make('duracao_segundos')->label('Tempo')
                    ->formatStateUsing(fn ($state): string => $state ? gmdate($state >= 3600 ? 'H:i:s' : 'i:s', $state) : '—'),
                Tables\Columns\TextColumn::make('origem')->label('Origem')->badge()->color('gray')->placeholder('—'),
            ])
            ->defaultSort('ocorrido_em', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')
                    ->options(collect(RelInteracao::TIPOS)->map(fn ($t) => $t[0])->all()),
            ])
            ->emptyStateHeading('Nenhuma interação registrada')
            ->emptyStateDescription('As ações da empresa no oravel.org e os contatos comerciais aparecem aqui em ordem cronológica.');
    }
}
