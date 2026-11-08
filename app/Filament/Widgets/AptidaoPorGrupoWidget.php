<?php

namespace App\Filament\Widgets;

use App\Models\AssetCategory;
use App\Services\Catalogo\Aptidao\AptidaoGate;
use App\Support\CatalogoAcesso;
use App\Support\Tenancy;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** G1 -- frota apta x inapta POR GRUPO. So com a chave do catalogo ligada e permissao de leitura. */
class AptidaoPorGrupoWidget extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Aptidão por grupo de equipamento';

    public static function canView(): bool
    {
        return CatalogoAcesso::podeLer() && AptidaoGate::ligada(Tenancy::current()?->id ?? auth()->user()?->tenant_id);
    }

    public function table(Table $table): Table
    {
        $conta = fn (string $status) => fn (Builder $q) => $q->where('aptidao_status', $status);

        return $table
            ->query(AssetCategory::query()->whereHas('assets')->withCount([
                'assets as total_count',
                'assets as aptos_count' => $conta('apto'),
                'assets as restricao_count' => $conta('apto_restricao'),
                'assets as inaptos_count' => $conta('inapto'),
            ]))
            ->defaultSort('inaptos_count', 'desc')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Grupo')->weight('bold'),
                Tables\Columns\TextColumn::make('total_count')->label('Ativos'),
                Tables\Columns\TextColumn::make('aptos_count')->label('Aptos')->color('success'),
                Tables\Columns\TextColumn::make('restricao_count')->label('Com restrição')->color('warning'),
                Tables\Columns\TextColumn::make('inaptos_count')->label('Inaptos')->color('danger')->weight('bold'),
            ]);
    }
}
