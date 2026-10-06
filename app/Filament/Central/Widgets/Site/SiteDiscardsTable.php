<?php

namespace App\Filament\Central\Widgets\Site;

use App\Models\WebDiscard;
use App\Support\WebAnalytics;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * O que o coletor descartou no período -- serve para conferir a Central com o Google Analytics:
 * o Analytics conta parte disso (robôs/servidores), a Central não. Quem usa ?notrack ou
 * "Não rastrear" nem chega ao servidor, então não aparece aqui.
 */
class SiteDiscardsTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Descartados pelo filtro (não entram nas visitas)';

    public function table(Table $table): Table
    {
        return $table
            ->description('Para conferir com o Google Analytics, que conta parte destes acessos. Quem usa ?notrack ou "Não rastrear" nem chega ao servidor.')
            ->query(fn (): Builder => WebDiscard::query()
                ->where('day', '>=', WebAnalytics::since($this->filters['period'] ?? '7')->toDateString())
                ->selectRaw('reason, sum(sessions) as sessions, sum(hits) as hits')
                ->groupBy('reason')
                ->reorder()
                ->orderByDesc('hits'))
            ->columns([
                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->weight('bold')
                    ->formatStateUsing(fn (string $state) => WebDiscard::reasonLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('sessions')->label('Sessões'),
                Tables\Columns\TextColumn::make('hits')->label('Páginas abertas'),
            ])
            ->paginated(false)
            ->emptyStateHeading('Nada descartado no período');
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->reason;
    }
}
