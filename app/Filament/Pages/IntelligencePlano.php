<?php

namespace App\Filament\Pages;

use App\Domain\Intelligence\Concerns\UsaResumoDoIntelligence;
use App\Domain\Intelligence\Services\PlanoDeEvolucao;
use App\Domain\Intelligence\Support\Graficos;
use Filament\Pages\Page;

/** Intelligence › Plano de evolução. Chave de contrato: IntelligencePlano0. */
class IntelligencePlano extends Page
{
    use UsaResumoDoIntelligence;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'Intelligence';

    protected static ?string $navigationLabel = 'Plano de evolução';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Plano de evolução';

    protected static ?string $slug = 'intelligence-plano';

    protected static string $view = 'filament.pages.intelligence-plano';

    protected function getHeaderActions(): array
    {
        return $this->acoesDeRelatorio('plano');
    }

    /** @return array{foco: ?string, itens: list<array>, prazos: array<int, list<array>>} */
    public function plano(array $resumo): array
    {
        $plano = app(PlanoDeEvolucao::class)->plano($resumo);
        $plano['equip'] = [30 => [], 60 => [], 90 => []];
        foreach ($this->insightsDeEquipamentos()['itens'] ?? [] as $i) {
            if ($i['acao'] !== null && isset($plano['equip'][$i['prazo']])) {
                $plano['equip'][$i['prazo']][] = $i;
            }
        }
        $plano['equip_total'] = array_sum(array_map('count', $plano['equip']));

        return $plano;
    }

    public function esforco(array $plano): ?array
    {
        return app(Graficos::class)->esforcoDoPlano($plano);
    }
}
