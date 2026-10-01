<?php

namespace App\Filament\Central\Widgets\Site;

use App\Models\WebVisit;
use App\Support\WebAnalytics;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SiteSourcesTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'De onde vêm os visitantes';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => WebVisit::query()
                ->where('started_at', '>=', WebAnalytics::since($this->filters['period'] ?? '7'))
                ->selectRaw("coalesce(nullif(utm_source, ''), nullif(referrer_host, ''), 'Direto') as origem, count(*) as visits, count(distinct visitor_token) as visitors, avg(duration_seconds) as avg_time")
                ->groupBy('origem')
                ->reorder()
                ->orderByDesc('visits'))
            ->columns([
                Tables\Columns\TextColumn::make('origem')->label('Origem')->weight('bold'),
                Tables\Columns\TextColumn::make('visits')->label('Visitas')->sortable(),
                Tables\Columns\TextColumn::make('visitors')->label('Visitantes')->sortable(),
                Tables\Columns\TextColumn::make('avg_time')->label('Tempo médio')->formatStateUsing(fn ($state) => WebAnalytics::duration($state)),
            ])
            ->defaultSort('visits', 'desc')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }

    public function getTableRecordKey(Model $record): string
    {
        return (string) $record->origem;
    }
}
