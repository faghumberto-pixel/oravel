<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\WebVisitResource\Pages;
use App\Models\WebVisit;
use App\Support\WebAnalytics;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Visitas ao SITE INSTITUCIONAL (somente leitura). Separado de "Acessos e
 * Visitantes" (SiteVisitResource), que mede o app.
 */
class WebVisitResource extends Resource
{
    protected static ?string $model = WebVisit::class;

    protected static ?string $navigationIcon = 'heroicon-o-cursor-arrow-rays';

    protected static ?string $navigationGroup = 'Site Institucional';

    protected static ?string $navigationLabel = 'Visitas do Site';

    protected static ?string $modelLabel = 'Visita do Site';

    protected static ?string $pluralModelLabel = 'Visitas do Site';

    protected static ?int $navigationSort = 1;

    protected static bool $isScopedToTenant = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('started_at')->label('Quando')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('landing_path')->label('Página de entrada')->limit(40)->searchable(),
                Tables\Columns\TextColumn::make('source')->label('Origem')->badge()->color('gray')
                    ->state(fn (WebVisit $r) => $r->sourceLabel()),
                Tables\Columns\TextColumn::make('utm_campaign')->label('Campanha')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('device_type')->label('Dispositivo')->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'mobile' => 'Celular', 'tablet' => 'Tablet', default => 'Desktop'
                    }),
                Tables\Columns\TextColumn::make('browser')->label('Navegador')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('os')->label('Sistema')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('page_views')->label('Páginas')->sortable(),
                Tables\Columns\TextColumn::make('duration_seconds')->label('Tempo ativo')->sortable()
                    ->formatStateUsing(fn ($state) => WebAnalytics::duration($state)),
                Tables\Columns\IconColumn::make('is_returning')->label('Voltou')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('ip_address')->label('IP')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('city')->label('Cidade')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('state')->label('UF')->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('device_type')->label('Dispositivo')
                    ->options(['desktop' => 'Desktop', 'mobile' => 'Celular', 'tablet' => 'Tablet']),
                Tables\Filters\SelectFilter::make('period')->label('Período')->options(WebAnalytics::periods())
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->where('started_at', '>=', WebAnalytics::since($data['value']))
                        : $query),
                Tables\Filters\TernaryFilter::make('is_returning')->label('Voltou ao site'),
                Tables\Filters\Filter::make('com_utm')->label('Veio de campanha (UTM)')
                    ->query(fn ($query) => $query->whereNotNull('utm_source')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Ver jornada')->icon('heroicon-o-map'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Visita')->schema([
                Infolists\Components\TextEntry::make('started_at')->label('Início')->dateTime('d/m/Y H:i'),
                Infolists\Components\TextEntry::make('duration_seconds')->label('Tempo ativo total')
                    ->formatStateUsing(fn ($state) => WebAnalytics::duration($state)),
                Infolists\Components\TextEntry::make('source')->label('Origem')->state(fn (WebVisit $r) => $r->sourceLabel()),
                Infolists\Components\TextEntry::make('referrer_url')->label('Veio de')->placeholder('Acesso direto')->limit(80),
                Infolists\Components\TextEntry::make('utm_campaign')->label('Campanha')->placeholder('—'),
                Infolists\Components\TextEntry::make('device')->label('Aparelho')
                    ->state(fn (WebVisit $r) => collect([$r->device_type, $r->browser, $r->os])->filter()->implode(' · ')),
                Infolists\Components\TextEntry::make('local')->label('Local / IP')
                    ->state(fn (WebVisit $r) => collect([$r->city ? $r->city.'/'.$r->state : null, $r->ip_address])->filter()->implode(' — ')),
            ])->columns(3),

            Infolists\Components\Section::make('Jornada: páginas visitadas, em ordem')->schema([
                Infolists\Components\RepeatableEntry::make('pageviews')->label('')->schema([
                    Infolists\Components\TextEntry::make('entered_at')->label('Hora')->dateTime('H:i:s'),
                    Infolists\Components\TextEntry::make('path')->label('Página')->weight('bold'),
                    Infolists\Components\TextEntry::make('active_seconds')->label('Tempo na página')
                        ->formatStateUsing(fn ($state) => WebAnalytics::duration($state)),
                    Infolists\Components\TextEntry::make('max_scroll')->label('Rolagem')->suffix('%'),
                ])->columns(4),
            ]),

            Infolists\Components\Section::make('Cliques')->schema([
                Infolists\Components\RepeatableEntry::make('events')->label('')->schema([
                    Infolists\Components\TextEntry::make('occurred_at')->label('Hora')->dateTime('H:i:s'),
                    Infolists\Components\TextEntry::make('label')->label('Clique')->weight('bold'),
                    Infolists\Components\TextEntry::make('path')->label('Na página'),
                ])->columns(3),
            ])->visible(fn (WebVisit $r) => $r->events()->exists()),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListWebVisits::route('/')];
    }
}
