<?php

namespace App\Filament\Pages;

use App\Domain\Intelligence\Concerns\UsaResumoDoIntelligence;
use App\Domain\Intelligence\Models\IntelligenceEquipamento;
use App\Domain\Intelligence\Services\AnaliseDeEquipamentos;
use App\Domain\Intelligence\Services\EquipamentosService;
use App\Domain\Intelligence\Support\Graficos;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Intelligence › Equipamentos: quais equipamentos explicam os números da empresa e o que fazer com cada grupo (revisar, trocar ou vender,
 * subutilizados, risco de parada). Lê o snapshot mais recente que tem a aba "Equipamentos". Chaves de contrato: menu_intelligence_equipamentos
 * (tela) e tabela_intelligence_equipamentos (dados).
 */
class IntelligenceEquipamentos extends Page
{
    use UsaResumoDoIntelligence {
        canAccess as canAccessBase;
    }

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Intelligence';

    protected static ?string $navigationLabel = 'Equipamentos';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Equipamentos';

    protected static ?string $slug = 'intelligence-equipamentos';

    protected static string $view = 'filament.pages.intelligence-equipamentos';

    #[Url]
    public string $busca = '';

    #[Url]
    public string $categoria = '';

    #[Url]
    public string $classificacao = '';

    #[Url]
    public string $ordem = 'gravidade';

    public int $limite = 50;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', IntelligenceEquipamento::class);
    }

    protected function getHeaderActions(): array
    {
        return $this->acoesDeRelatorio('equipamentos', fn () => $this->snapshot() !== null);
    }

    private function tenantId(): ?string
    {
        return Tenancy::current()?->id;
    }

    /** @return ?array{id: string, data: string} */
    public function snapshot(): ?array
    {
        $t = $this->tenantId();

        return $t ? app(EquipamentosService::class)->snapshot($t) : null;
    }

    public function updated(string $propriedade): void
    {
        if (in_array($propriedade, ['busca', 'categoria', 'classificacao', 'ordem'], true)) {
            $this->limite = 50;
        }
        if ($propriedade === 'ordem' && ! array_key_exists($this->ordem, EquipamentosService::ORDENS)) {
            $this->ordem = 'gravidade';
        }
    }

    public function filtrarCard(string $classe): void
    {
        $this->classificacao = $this->classificacao === $classe || ! array_key_exists($classe, AnaliseDeEquipamentos::ROTULOS) ? '' : $classe;
        $this->limite = 50;
    }

    public function mostrarMais(): void
    {
        $this->limite += 50;
    }

    /** @return array{busca: string, categoria: string, classificacao: string, ordem: string} */
    private function filtros(): array
    {
        return ['busca' => $this->busca, 'categoria' => $this->categoria, 'classificacao' => $this->classificacao, 'ordem' => $this->ordem];
    }

    /** @return array<string, mixed> tudo que a tela precisa, calculado uma vez por renderização */
    public function dados(): array
    {
        $t = $this->tenantId();
        $snap = $this->snapshot();
        if (! $t || ! $snap) {
            return [];
        }
        $svc = app(EquipamentosService::class);
        $g = new Graficos;
        $pareto = $svc->pareto($t, $snap['id']);

        return [
            'snapshot' => $snap,
            'resumo' => $svc->resumo($t, $snap['id']),
            'tabela' => $svc->linhas($t, $snap['id'], $this->filtros(), $this->limite),
            'pareto' => $pareto,
            'graficos' => ['pareto' => $g->paretoEquipamentos($pareto), 'bolhas' => $g->valorReceita($svc->bolhas($t, $snap['id']))],
        ];
    }

    /** Excel com TODAS as linhas do filtro atual (até o limite do importador). */
    public function exportar(): ?StreamedResponse
    {
        $t = $this->tenantId();
        $snap = $this->snapshot();
        if (! $t || ! $snap) {
            return null;
        }
        $linhas = app(EquipamentosService::class)->todas($t, $snap['id'], $this->filtros());
        $rot = AnaliseDeEquipamentos::ROTULOS;

        return response()->streamDownload(function () use ($linhas, $rot) {
            $w = new Writer;
            $w->openToFile('php://output');
            $w->getCurrentSheet()->setName('Equipamentos');
            $w->addRow(Row::fromValues(['Nº Patrimônio', 'Equipamento', 'Categoria', 'Situação', 'Motivo', 'Tendência', 'Valor (R$)', 'Receita no mês (R$)', 'Custo no mês (R$)', 'Custo acumulado (R$)',
                'Custo ÷ valor (%)', 'Utilização (%)', 'Disponibilidade (%)', 'Retorno anual (%)', 'Reincidência (%)', 'Idade (anos)']));
            foreach ($linhas as $e) {
                $w->addRow(Row::fromValues([$e['patrimonio'], $e['nome'], $e['categoria'], $rot[$e['classificacao']] ?? $e['classificacao'], $e['motivo'], $e['tendencia'], $e['valor'], $e['receita'],
                    $e['custo_mes'], $e['custo_acumulado'], $e['razao_custo_valor'] === null ? null : round($e['razao_custo_valor'] * 100, 1), $e['utilizacao_pct'], $e['disponibilidade_pct'],
                    $e['retorno_anual_pct'], $e['reincidencia_pct'], $e['idade_anos']]));
            }
            $w->close();
        }, 'equipamentos-intelligence.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
