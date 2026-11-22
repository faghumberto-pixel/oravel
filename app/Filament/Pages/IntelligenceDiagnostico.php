<?php

namespace App\Filament\Pages;

use App\Domain\Intelligence\Concerns\UsaResumoDoIntelligence;
use App\Domain\Intelligence\Services\PlanoDeEvolucao;
use App\Domain\Intelligence\Support\Graficos;
use Filament\Pages\Page;

/** Intelligence › Diagnóstico. Chave de contrato: IntelligenceDiagnostico0. */
class IntelligenceDiagnostico extends Page
{
    use UsaResumoDoIntelligence;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Intelligence';

    protected static ?string $navigationLabel = 'Diagnóstico';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Diagnóstico: principais pontos de atenção';

    protected static ?string $slug = 'intelligence-diagnostico';

    protected static string $view = 'filament.pages.intelligence-diagnostico';

    protected function getHeaderActions(): array
    {
        return $this->acoesDeRelatorio('diagnostico');
    }

    /** @return array{atencao: list<array>, fortes: list<array>, areas: list<array>} */
    public function diagnostico(array $resumo): array
    {
        return app(PlanoDeEvolucao::class)->diagnostico($resumo);
    }

    /** @return array<string, mixed> */
    public function graficos(array $resumo): array
    {
        $g = app(Graficos::class);

        return ['notas' => $g->notasPorIndicador($resumo), 'prioridade' => $g->matrizPrioridade($resumo), 'polar' => $g->areasPolar($resumo)];
    }
}
