<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\FrotaCustoAvulso;
use App\Services\Frota\CustoFrotaService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/** Custo total por veículo no período: combustível, manutenção, pneus, baterias, óleo, multas, sinistros, seguro/IPVA e outros. */
class FrotaCustoPorVeiculo extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Custo por veículo';

    protected static ?int $navigationSort = 13;

    protected static ?string $title = 'Custo total por veículo';

    protected static ?string $slug = 'frota-custo-por-veiculo';

    protected static string $view = 'filament.pages.frota-custo-por-veiculo';

    public int $meses = 3;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', FrotaCustoAvulso::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** @return array<string, string> */
    public function nomesComponentes(): array
    {
        return CustoFrotaService::componentes();
    }

    /** @return Collection<int, array<string, mixed>> veículos com custo no período, do mais caro para o mais barato */
    public function linhas(): Collection
    {
        $meses = in_array($this->meses, [1, 3, 6, 12], true) ? $this->meses : 3;
        $servico = new CustoFrotaService;

        return Asset::query()->where('grupo', Asset::GRUPO_VEICULO)->orderBy('placa')->get()
            ->map(fn (Asset $v) => ['veiculo' => $v] + $servico->veiculo($v, $meses))
            ->filter(fn (array $l) => $l['total'] > 0)
            ->sortByDesc('total')->values();
    }

    /** @param  Collection<int, array<string, mixed>>  $linhas */
    public function totais(Collection $linhas): array
    {
        $porComponente = collect(CustoFrotaService::componentes())->map(fn ($n, $k) => round((float) $linhas->sum(fn ($l) => $l['componentes'][$k]), 2))->all();

        return ['componentes' => $porComponente, 'total' => round((float) $linhas->sum('total'), 2)];
    }
}
