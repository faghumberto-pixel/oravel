<?php

namespace Tests\Feature;

use App\Filament\Resources\AIAnalysisResource;
use App\Models\AIAnalysis;
use Tests\TestCase;

/** A análise de IA da O.S. inteira abre como página legível/imprimível e tem rótulo próprio. Sem banco real (sqlite em memória). */
class AnaliseOsRelatorioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        app('db')->purge();
    }

    private function analise(): AIAnalysis
    {
        $a = new AIAnalysis([
            'type' => AIAnalysis::TYPE_ORDEM_SERVICO,
            'status' => AIAnalysis::STATUS_CONCLUIDA,
            'context' => ['os' => ['numero' => 'OS-2026-0042', 'tipo' => 'Corretiva', 'status' => 'Em Andamento'], 'ativo' => ['nome' => 'Plataforma Haulotte H12SX', 'patrimonio' => 'PAT-0004']],
            'response' => [
                'resumo' => 'O.S. corretiva parada aguardando peça há 6 dias.', 'avaliacao_geral' => 'atencao',
                'prazo_e_sla' => 'SLA de resolução estourou há 2 dias.', 'custo' => 'R$ 4.200, 35% acima da média do tipo.',
                'pecas_e_materiais' => 'Vedação do cilindro sem baixa de estoque.', 'checklist_e_registro' => ['Checklist com 3 itens pendentes'],
                'riscos' => ['Equipamento locado sem liberação formal'], 'pontos_de_atencao' => ['Sem foto de evidência'],
                'risco_retrabalho' => 'alto', 'motivo_risco_retrabalho' => 'Mesmo defeito reaberto duas vezes.',
                'recomendacoes' => ['Registrar a baixa da vedação'],
                'proximos_passos' => [['acao' => 'Cobrar o fornecedor da peça', 'responsavel_sugerido' => 'Compras', 'prazo' => 'hoje']],
            ],
        ]);
        $a->setRelation('user', null)->setRelation('maintenanceOrder', null)->setRelation('equipmentDamage', null);

        return $a;
    }

    public function test_relatorio_imprimivel_mostra_a_analise_da_os(): void
    {
        $html = view('pdf.ai_analysis_report', ['analysis' => $this->analise(), 'typeLabel' => 'Análise da O.S.', 'generatedAt' => '16/11/2026 12:00'])->render();

        foreach (['OS-2026-0042', 'Plataforma Haulotte H12SX', 'estourou há 2 dias', '35% acima da média', 'Vedação do cilindro', 'Mesmo defeito reaberto', 'Cobrar o fornecedor da peça', 'Próximos passos'] as $trecho) {
            $this->assertStringContainsString($trecho, $html);
        }
    }

    public function test_tela_do_painel_mostra_a_analise_da_os(): void
    {
        $a = $this->analise();
        $html = view('filament.infolists.ai-analysis-result', ['getRecord' => fn () => $a])->render();

        $this->assertStringContainsString('OS-2026-0042', $html);
        $this->assertStringContainsString('Registrar a baixa da vedação', $html);
        $this->assertStringContainsString('Risco de retrabalho', $html);
    }

    public function test_rotulo_e_referencia_da_analise_da_os(): void
    {
        $this->assertSame('Análise da O.S.', AIAnalysisResource::typeLabels()[AIAnalysis::TYPE_ORDEM_SERVICO]);
        $this->assertSame('OS-2026-0042', $this->analise()->reference_label);
    }
}
