<?php

namespace App\Filament\Pages;

use App\Domain\Intelligence\Concerns\UsaResumoDoIntelligence;
use App\Domain\Intelligence\Support\Graficos;
use Filament\Pages\Page;

/** Intelligence › Frota, finanças e oficina. Chave de contrato: IntelligenceOperacao0. */
class IntelligenceOperacao extends Page
{
    use UsaResumoDoIntelligence;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = 'Intelligence';

    protected static ?string $navigationLabel = 'Frota, finanças e oficina';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Frota, finanças e oficina';

    protected static ?string $slug = 'intelligence-operacao';

    protected static string $view = 'filament.pages.intelligence-operacao';

    /** @return array<string, mixed> */
    public function graficos(array $resumo): array
    {
        $g = app(Graficos::class);

        return $g->todos($resumo) + ['kpis' => $g->kpis($resumo)];
    }
}
