<?php

namespace App\Filament\Central\Widgets\Site;

use App\Models\WebEvent;
use App\Support\WebAnalytics;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SiteClicksTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Cliques em CTAs e links';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => WebEvent::query()
                ->where('type', 'click')
                ->where('occurred_at', '>=', WebAnalytics::since($this->filters['period'] ?? '7'))
                ->selectRaw('label, count(*) as clicks, count(distinct web_visit_id) as visitors')
                ->groupBy('label')
                ->reorder()
                ->orderByDesc('clicks'))
            ->columns([
                Tables\Columns\TextColumn::make('label')->label('Clique')->weight('bold')->limit(50),
                Tables\Columns\TextColumn::make('clicks')->label('Cliques')->sortable(),
                Tables\Columns\TextColumn::make('visitors')->label('Visitantes')->sortable(),
            ])
            ->defaultSort('clicks', 'desc')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Nenhum clique registrado no período');
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->label;
    }
}
