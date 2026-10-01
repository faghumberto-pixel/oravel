<?php

namespace App\Filament\Central\Widgets\Site;

use App\Models\WebPageview;
use App\Support\WebAnalytics;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SiteTopPagesTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Páginas mais acessadas';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => WebPageview::query()
                ->where('entered_at', '>=', WebAnalytics::since($this->filters['period'] ?? '7'))
                ->selectRaw('path, count(*) as views, count(distinct web_visit_id) as visits, avg(active_seconds) as avg_time, avg(max_scroll) as avg_scroll')
                ->groupBy('path')
                ->reorder()
                ->orderByDesc('views'))
            ->columns([
                Tables\Columns\TextColumn::make('path')->label('Página')->weight('bold')->limit(60)->searchable(),
                Tables\Columns\TextColumn::make('views')->label('Visualizações')->sortable(),
                Tables\Columns\TextColumn::make('visits')->label('Visitas')->sortable(),
                Tables\Columns\TextColumn::make('avg_time')->label('Tempo médio na página')->sortable()
                    ->formatStateUsing(fn ($state) => WebAnalytics::duration($state)),
                Tables\Columns\TextColumn::make('avg_scroll')->label('Rolagem média')->sortable()
                    ->formatStateUsing(fn ($state) => round((float) $state).'%'),
            ])
            ->defaultSort('views', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10);
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->path;
    }
}
