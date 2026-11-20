<?php

namespace App\Relacionamento\Filament\Widgets;

use App\Relacionamento\Models\RelInteracao;
use App\Support\WebAnalytics;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Ferramentas do oravel.org concluídas no período (vem do Relacionamento, não das visitas: só vale o filtro de período). */
class OrgFerramentasTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Ferramentas concluídas';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->description('Só vale o filtro de período. "Pessoas" são os usos já ligados a alguém que se identificou.')
            ->query(fn (): Builder => RelInteracao::query()
                ->where('tipo', RelInteracao::TIPO_FERRAMENTA)
                ->whereNotNull('conteudo_slug')
                ->where('ocorrido_em', '>=', WebAnalytics::since($this->filters['period'] ?? '7'))
                ->selectRaw('conteudo_slug, max(titulo) as titulo, count(*) as usos, avg(duracao_segundos) as tempo, count(distinct contato_id) as pessoas')
                ->groupBy('conteudo_slug')
                ->reorder()
                ->orderByDesc('usos'))
            ->columns([
                Tables\Columns\TextColumn::make('titulo')->label('Ferramenta')->weight('bold')->limit(60),
                Tables\Columns\TextColumn::make('usos')->label('Conclusões')->sortable(),
                Tables\Columns\TextColumn::make('tempo')->label('Tempo médio de uso')->sortable()
                    ->formatStateUsing(fn ($state) => $state ? WebAnalytics::duration($state) : '—'),
                Tables\Columns\TextColumn::make('pessoas')->label('Pessoas')->sortable(),
            ])
            ->defaultSort('usos', 'desc')
            ->emptyStateHeading('Nenhuma ferramenta concluída no período')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10);
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->conteudo_slug;
    }
}
