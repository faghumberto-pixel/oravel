<?php

namespace App\Filament\Resources\ContractResource\Widgets;

use App\Models\Contract;
use App\Models\ContractAnalysis;
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
            $this->loadAnalysisFromCache();
        }
    }

    public function loadAnalysisFromCache(): void
    {
        if (!$this->contract?->id) {
            return;
        }

        $cached = ContractAnalysis::where('contract_id', $this->contract->id)->first();
        if ($cached) {
            $this->analysis = $cached->analysis;
        }
    }

    public function loadAnalysis(): void
    {
        if (!$this->contract?->id) {
            return;
        }

        $this->loading = true;

        try {
            $prompt = $this->buildAnalysisPrompt();
            $analysisText = $this->analyzeWithAI($prompt);

            ContractAnalysis::updateOrCreate(
                ['contract_id' => $this->contract->id],
                ['analysis' => $analysisText]
            );

            $this->analysis = $analysisText;
        } catch (\Exception $e) {
            $this->analysis = "Erro ao analisar contrato: " . $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    private function buildAnalysisPrompt(): string
    {
        $status = $this->contract->is_active ? 'Ativo' : 'Inativo';
        $clientName = $this->contract->client?->name ?? 'N/A';
        $assetName = $this->contract->asset?->name ?? 'N/A';
        $startDate = $this->contract->start_date?->format('d/m/Y') ?? 'N/A';
        $endDate = $this->contract->end_date?->format('d/m/Y') ?? 'N/A';

        return <<<PROMPT
Analise o seguinte contrato e forneça um resumo executivo com insights importantes:

**Número do Contrato:** {$this->contract->contract_number}
**Cliente:** {$clientName}
**Equipamento:** {$assetName}
**Data de Início:** {$startDate}
**Data de Vencimento:** {$endDate}
**Valor:** R$ {$this->contract->price}
**Status:** {$status}
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
            'model' => 'claude-sonnet-5',
            'max_tokens' => 800,
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
