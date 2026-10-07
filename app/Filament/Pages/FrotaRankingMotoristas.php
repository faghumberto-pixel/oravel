<?php

namespace App\Filament\Pages;

use App\Models\FleetDriver;
use App\Models\FrotaMulta;
use App\Services\Frota\IndicadoresMotoristaService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/** Ranking de motoristas: indicadores de condução no período (para conversar com o motorista, não uma nota). */
class FrotaRankingMotoristas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $navigationLabel = 'Ranking de motoristas';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Ranking de motoristas';

    protected static ?string $slug = 'frota-ranking-motoristas';

    protected static string $view = 'filament.pages.frota-ranking-motoristas';

    public int $meses = 3;

    public string $ordem = 'ocorrencias_por_mil_km';

    /** @return array<string, string> */
    public static function ordens(): array
    {
        return [
            'ocorrencias_por_mil_km' => 'Mais ocorrências por 1.000 km', 'multas' => 'Mais multas', 'pontos_12_meses' => 'Mais pontos na CNH (12 meses)',
            'sinistros' => 'Mais sinistros', 'km' => 'Mais km rodado', 'consumo_km_l' => 'Melhor consumo (km/l)',
        ];
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', FrotaMulta::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function linhas(): Collection
    {
        $meses = in_array($this->meses, [1, 3, 6, 12], true) ? $this->meses : 3;
        $ordem = array_key_exists($this->ordem, self::ordens()) ? $this->ordem : 'ocorrencias_por_mil_km';
        $servico = new IndicadoresMotoristaService;

        return FleetDriver::query()->where('active', true)->orderBy('name')->get()
            ->map(fn (FleetDriver $m) => ['motorista' => $m] + $servico->motorista($m, $meses))
            ->filter(fn (array $l) => $l['saidas'] > 0 || $l['multas'] > 0 || $l['sinistros'] > 0)
            ->sortByDesc(fn (array $l) => $l[$ordem] ?? -1)->values();
    }
}
