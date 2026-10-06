<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\FrotaBateria;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Quantas baterias cada veículo consumiu no período. Muita troca em pouco tempo costuma indicar problema
 * no alternador ou no sistema de carga, não na bateria.
 */
class FrotaBateriasPorVeiculo extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Baterias por veículo';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Baterias por veículo';

    protected static ?string $slug = 'frota-baterias-por-veiculo';

    protected static string $view = 'filament.pages.frota-baterias-por-veiculo';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', FrotaBateria::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** Meses do período escolhido no filtro (padrão 12). */
    public function meses(): int
    {
        return (int) ($this->tableFilters['periodo']['value'] ?? 12) ?: 12;
    }

    public function consulta(): Builder
    {
        $desde = now()->subMonths($this->meses());

        return Asset::query()
            ->where('assets.grupo', Asset::GRUPO_VEICULO)
            ->withCount([
                'instalacoesBateria as baterias_no_periodo' => fn ($q) => $q->where('instalado_em', '>=', $desde),
                'bateriasMontadas as baterias_montadas',
            ])
            ->withMax(['instalacoesBateria as ultima_troca' => fn ($q) => $q], 'instalado_em');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->consulta())
            ->defaultSort('baterias_no_periodo', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('placa')->label('Placa')->weight('bold')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('name')->label('Veículo')->searchable(),
                Tables\Columns\TextColumn::make('baterias_no_periodo')->label('Baterias instaladas no período')->sortable()->badge()
                    ->color(fn (int $state) => $state >= 3 ? 'danger' : ($state === 2 ? 'warning' : 'gray'))
                    ->description(fn (Asset $record) => $record->baterias_no_periodo >= 2 ? 'Verifique o alternador / sistema de carga' : null),
                Tables\Columns\TextColumn::make('baterias_montadas')->label('Montadas agora')->sortable(),
                Tables\Columns\TextColumn::make('ultima_troca')->label('Última instalação')->dateTime('d/m/Y')->placeholder('—')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('periodo')->label('Período')
                    ->options(['3' => 'Últimos 3 meses', '6' => 'Últimos 6 meses', '12' => 'Últimos 12 meses', '24' => 'Últimos 24 meses'])
                    ->default('12')->selectablePlaceholder(false)
                    ->query(fn (Builder $query) => $query),
            ])
            ->emptyStateHeading('Nenhum veículo cadastrado');
    }
}
