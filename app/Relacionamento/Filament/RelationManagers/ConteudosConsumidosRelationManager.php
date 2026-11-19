<?php

namespace App\Relacionamento\Filament\RelationManagers;

use App\Relacionamento\Models\RelInteracao;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Aba "Conteúdos consumidos": so' o que a empresa leu, assistiu, usou ou baixou no oravel.org (e e-mails). Somente leitura. */
class ConteudosConsumidosRelationManager extends RelationManager
{
    protected static string $relationship = 'relInteracoes';

    protected static ?string $title = 'Conteúdos consumidos';

    protected static ?string $icon = 'heroicon-o-book-open';

    private const TIPOS = [
        RelInteracao::TIPO_ARTIGO, RelInteracao::TIPO_VIDEO, RelInteracao::TIPO_FERRAMENTA, RelInteracao::TIPO_PDF,
        RelInteracao::TIPO_GAZETA, RelInteracao::TIPO_EMAIL_ABERTO, RelInteracao::TIPO_EMAIL_CLIQUE,
    ];

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('tipo', self::TIPOS)->with('conteudo'))
            ->columns([
                Tables\Columns\TextColumn::make('ocorrido_em')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('tipo')->label('Tipo')->badge()
                    ->formatStateUsing(fn (string $state): string => RelInteracao::rotulo($state))
                    ->color(fn (string $state): string => RelInteracao::cor($state)),
                Tables\Columns\TextColumn::make('titulo')->label('Conteúdo')->weight('bold')->wrap()->placeholder('—'),
                Tables\Columns\TextColumn::make('conteudo.tema')->label('Tema')->badge()->color('gray')->placeholder('—'),
                Tables\Columns\TextColumn::make('duracao_segundos')->label('Tempo')
                    ->formatStateUsing(fn ($state): string => $state ? gmdate($state >= 3600 ? 'H:i:s' : 'i:s', $state) : '—'),
                Tables\Columns\TextColumn::make('origem')->label('Origem')->badge()->color('gray')->placeholder('—'),
                Tables\Columns\TextColumn::make('resultado')->label('Resultado')->wrap()->limit(100)->placeholder('—'),
            ])
            ->defaultSort('ocorrido_em', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')->label('Tipo')
                    ->options(collect(self::TIPOS)->mapWithKeys(fn ($t) => [$t => RelInteracao::rotulo($t)])->all()),
            ])
            ->emptyStateHeading('Nenhum conteúdo consumido ainda');
    }
}
