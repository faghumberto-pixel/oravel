<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\FrotaAbastecimento;
use App\Services\Frota\AbastecimentoService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/** Consumo por veículo no período: litros, gasto, km, km/l médio, custo por km e último consumo comparado ao habitual. */
class FrotaConsumoPorVeiculo extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Consumo por veículo';

    protected static ?int $navigationSort = 12;

    protected static ?string $title = 'Consumo por veículo';

    protected static ?string $slug = 'frota-consumo-por-veiculo';

    protected static string $view = 'filament.pages.frota-consumo-por-veiculo';

    public int $meses = 3;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', FrotaAbastecimento::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function linhas(): Collection
    {
        $meses = in_array($this->meses, [1, 3, 6, 12], true) ? $this->meses : 3;

        return Asset::query()->where('grupo', Asset::GRUPO_VEICULO)->orderBy('placa')->get()
            ->map(function (Asset $v) use ($meses) {
                $ultimo = FrotaAbastecimento::where('ativo_id', $v->id)->whereNotNull('consumo_km_l')->latest('abastecido_em')->first();

                return ['veiculo' => $v, 'resumo' => AbastecimentoService::resumo($v, $meses), 'ultimo' => $ultimo, 'desvio' => $ultimo ? AbastecimentoService::desvio($ultimo) : null];
            })
            ->filter(fn (array $l) => $l['resumo']['abastecimentos'] > 0)
            ->values();
    }
}
