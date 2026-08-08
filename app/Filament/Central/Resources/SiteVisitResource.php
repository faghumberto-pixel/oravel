<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\SiteVisitResource\Pages;
use App\Models\SiteVisit;
use App\Models\Tenant;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * So' leitura -- e' dado de tracking (TrackSiteVisit), nao CRUD
 * operacional. Ver CLAUDE.md/plano: acompanhamento de acessos/visitantes
 * cross-tenant no central.
 */
class SiteVisitResource extends Resource
{
    protected static ?string $model = SiteVisit::class;

    protected static ?string $navigationIcon = 'heroicon-o-cursor-arrow-rays';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $navigationLabel = 'Acessos e Visitantes';

    protected static ?string $pluralModelLabel = 'Acessos e Visitantes';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->placeholder('Sem tenant')
                    ->searchable(),

                Tables\Columns\TextColumn::make('entry_panel')
                    ->label('Painel')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'admin' => 'Admin',
                        'central' => 'Central',
                        'public' => 'Público',
                        default => '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'admin' => 'primary',
                        'central' => 'crmPurple',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('landing_path')
                    ->label('Página de entrada')
                    ->limit(40)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('referrer_host')
                    ->label('Referência')
                    ->placeholder('Direto')
                    ->searchable(),

                Tables\Columns\TextColumn::make('utm_source')
                    ->label('UTM Source')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('utm_medium')
                    ->label('UTM Medium')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('utm_campaign')
                    ->label('UTM Campaign')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('device_type')
                    ->label('Dispositivo')
                    ->icon(fn (?string $state): string => match ($state) {
                        'mobile' => 'heroicon-o-device-phone-mobile',
                        'tablet' => 'heroicon-o-device-tablet',
                        'bot' => 'heroicon-o-cpu-chip',
                        default => 'heroicon-o-computer-desktop',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'mobile' => 'Celular',
                        'tablet' => 'Tablet',
                        'bot' => 'Bot',
                        default => 'Desktop',
                    }),

                Tables\Columns\TextColumn::make('page_views')
                    ->label('Páginas'),

                Tables\Columns\TextColumn::make('duration_seconds')
                    ->label('Duração')
                    ->formatStateUsing(fn (int $state): string => sprintf('%d:%02d', intdiv($state, 60), $state % 60)),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuário')
                    ->placeholder('Visitante'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->options(fn () => Tenant::orderBy('name')->pluck('name', 'id')),

                Tables\Filters\SelectFilter::make('entry_panel')
                    ->label('Painel')
                    ->options([
                        'admin' => 'Admin',
                        'central' => 'Central',
                        'public' => 'Público',
                    ]),

                Tables\Filters\SelectFilter::make('utm_source')
                    ->label('UTM Source')
                    ->options(fn () => SiteVisit::query()
                        ->whereNotNull('utm_source')
                        ->distinct()
                        ->pluck('utm_source', 'utm_source')),

                Tables\Filters\Filter::make('started_at')
                    ->form([
                        DatePicker::make('from')->label('De'),
                        DatePicker::make('until')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('started_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('started_at', '<=', $date));
                    }),
            ])
            ->defaultSort('started_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiteVisits::route('/'),
        ];
    }
}
