<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\TenantEventResource\Pages;
use App\Models\TenantEvent;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Histórico de todos os clientes (somente leitura): assinou, pagou, atrasou, anotações. */
class TenantEventResource extends Resource
{
    protected static ?string $model = TenantEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $navigationLabel = 'Histórico de Clientes';

    protected static ?string $modelLabel = 'Evento do Cliente';

    protected static ?string $pluralModelLabel = 'Histórico de Clientes';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes()->with(['tenant', 'actor']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')->label('Quando')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')->label('Cliente')->searchable()->sortable()->weight('bold')
                    ->url(fn (TenantEvent $r) => TenantResource::getUrl('edit', ['record' => $r->tenant_id])),
                Tables\Columns\TextColumn::make('event_type')->label('Tipo')->badge()
                    ->formatStateUsing(fn ($state) => TenantEvent::types()[$state]['label'] ?? $state)
                    ->color(fn ($state) => TenantEvent::types()[$state]['color'] ?? 'gray'),
                Tables\Columns\TextColumn::make('title')->label('O que aconteceu')->wrap()->searchable(),
                Tables\Columns\TextColumn::make('description')->label('Detalhes')->wrap()->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('actor.name')->label('Registrado por')->placeholder('Sistema')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')->label('Cliente')->relationship('tenant', 'name')->searchable(),
                Tables\Filters\SelectFilter::make('event_type')->label('Tipo')
                    ->options(collect(TenantEvent::types())->map(fn ($t) => $t['label'])->all()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTenantEvents::route('/')];
    }
}
