<?php

namespace App\Filament\Resources\ContractResource\Widgets;

use App\Models\Contract;
use Filament\Widgets\Widget;

class ContractAIAnalysisWidget extends Widget
{
    protected static string $view = 'filament.resources.contract-resource.widgets.contract-ai-analysis-widget';

    public ?Contract $contract = null;

    public ?string $analysis = null;

    public bool $loading = false;

    public function mount(): void
    {
        if ($this->contract?->id) {
            $this->loadAnalysis();
        }
    }

    public function loadAnalysis(): void
    {
        $this->loading = true;

        try {
            $prompt = $this->buildAnalysisPrompt();
            $this->analysis = $this->analyzeWithAI($prompt);
        } catch (\Exception $e) {
            $this->analysis = "Erro ao analisar contrato: " . $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    private function buildAnalysisPrompt(): string
    {
        return <<<PROMPT
Analise o seguinte contrato e forneça um resumo executivo com insights importantes:

**Número do Contrato:** {$this->contract->contract_number}
**Cliente:** {$this->contract->client?->name}
**Equipamento:** {$this->contract->asset?->name}
**Data de Início:** {$this->contract->start_date?->format('d/m/Y')}
**Data de Vencimento:** {$this->contract->end_date?->format('d/m/Y')}
**Valor:** R$ {$this->contract->price}
**Status:** {$this->contract->is_active ? 'Ativo' : 'Inativo'}
**Tipo de Faturamento:** {$this->contract->billing_type}

Por favor, forneça:
1. **Resumo Executivo** - Descrição breve do contrato
2. **Pontos Críticos** - Datas importantes, prazos e renovações
3. **Recomendações** - Ações recomendadas para o gerenciamento do contrato
4. **Alertas** - Qualquer risco ou questão que precise de atenção

Seja conciso e direto ao ponto.
PROMPT;
    }

    private function analyzeWithAI(string $prompt): string
    {
        $client = app('anthropic');

        $response = $client->messages()->create([
            'model' => 'claude-opus-5',
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        return $response->content[0]->text ?? 'Sem análise disponível';
    }
}
