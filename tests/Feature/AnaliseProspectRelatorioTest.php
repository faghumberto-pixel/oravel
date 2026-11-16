<?php

namespace Tests\Feature;

use App\Models\AIAnalysis;
use Tests\TestCase;

/**
 * A análise de IA de um prospect abre como página (imprimível), não como pop-up com texto corrido.
 * Sem banco real: o modelo nem é salvo e a conexão é forçada para sqlite em memória.
 */
class AnaliseProspectRelatorioTest extends TestCase
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
            'type' => AIAnalysis::TYPE_PROSPECT,
            'status' => AIAnalysis::STATUS_CONCLUIDA,
            'response' => [
                'prioridade' => 'alta', 'qualificacao' => 'quente', 'risco_perda' => 'Alto - 15 dias sem resposta',
                'proximos_passos' => [['acao' => 'Ligar para o dono', 'prazo_dias' => 2, 'canal' => 'telefone', 'roteiro' => 'Perguntar sobre o horímetro']],
                'mensagem_sugerida' => "Olá, tudo bem?\nVi que vocês crescem.", 'pontos_atencao' => ['Concorrente já atende'],
            ],
        ]);
        $a->setRelation('user', null)->setRelation('prospect', null)->setRelation('equipmentDamage', null);

        return $a;
    }

    public function test_relatorio_imprimivel_mostra_a_analise_do_prospect(): void
    {
        $html = view('pdf.ai_analysis_report', ['analysis' => $this->analise(), 'typeLabel' => 'Análise de Prospect', 'generatedAt' => '16/11/2026 12:00'])->render();

        foreach (['Ligar para o dono', 'Perguntar sobre o horímetro', 'Alto - 15 dias sem resposta', 'Concorrente já atende', 'Vi que vocês crescem.', 'Próximos passos'] as $trecho) {
            $this->assertStringContainsString($trecho, $html);
        }
    }

    public function test_tela_do_painel_mostra_a_analise_do_prospect(): void
    {
        $a = $this->analise();
        $html = view('filament.infolists.ai-analysis-result', ['getRecord' => fn () => $a])->render();

        $this->assertStringContainsString('Ligar para o dono', $html);
        $this->assertStringContainsString('Mensagem sugerida', $html);
    }
}
