<?php

namespace App\Services;

use App\Models\AIAnalysis;
use App\Models\CrmLead;

/**
 * Analise de risco de perda de um Lead do CRM comercial via Claude API.
 * Mesmo padrao de degradacao de EquipmentDamageDiagnosisService: sem
 * ANTHROPIC_API_KEY ou com falha de API, marca a analise como 'falhou'
 * (logada), nunca quebra a tela do Lead. Chamada real feita via
 * App\Services\AnthropicApiClient (compartilhado com os outros servicos
 * de IA).
 */
class CommercialLeadAnalysisService
{
    public function __construct(private AnthropicApiClient $client) {}

    public function analyze(CrmLead $lead, string $userId): AIAnalysis
    {
        $context = $this->buildContext($lead);

        $analysis = AIAnalysis::create([
            'tenant_id' => $lead->tenant_id,
            'user_id' => $userId,
            'type' => AIAnalysis::TYPE_COMERCIAL,
            'context' => $context,
            'status' => AIAnalysis::STATUS_PENDENTE,
        ]);

        $result = $this->client->send(
            $this->systemPrompt(),
            "Dados do lead:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        if (! $result['ok']) {
            $analysis->update(['status' => AIAnalysis::STATUS_FALHOU, 'error' => $result['error']]);

            return $analysis;
        }

        $parsed = $this->client->parseJson($result['text']);

        if ($parsed === null) {
            $analysis->update([
                'status' => AIAnalysis::STATUS_FALHOU,
                'error' => 'A resposta da IA não veio em um formato reconhecível.',
                'response' => ['raw' => $result['text']],
            ]);

            return $analysis;
        }

        $analysis->update(['status' => AIAnalysis::STATUS_CONCLUIDA, 'response' => $parsed]);

        return $analysis;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            Você é um consultor de vendas B2B especializado em locação e manutenção de
            equipamentos pesados. Analise o lead recebido (funil, tempo parado no
            estágio atual, histórico de interações, valor estimado, taxa média de
            conversão da empresa) e avalie o risco de perda.

            Responda APENAS com um objeto JSON válido, sem markdown, sem crases, sem
            texto antes ou depois, no formato exato:

            {
              "probabilidade_perda": "string (ex: 'Alta (≈70%)' com uma frase de justificativa)",
              "recomendacoes": ["string", "string"],
              "email_sugerido": "string ou null (um e-mail curto e pronto para enviar ao lead, em português, se fizer sentido pelo estágio dele)"
            }
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(CrmLead $lead): array
    {
        $lastInteraction = $lead->interactions()->latest('contact_date')->first();

        $diasNoEstagio = $lead->updated_at ? now()->diffInDays($lead->updated_at) : null;
        $diasSemContato = $lastInteraction?->contact_date ? now()->diffInDays($lastInteraction->contact_date) : null;

        // updated_at como aproximacao de "quando entrou no estagio atual" --
        // nao existe converted_at/stage_changed_at dedicado no model hoje.
        $convertidos = CrmLead::where('tenant_id', $lead->tenant_id)
            ->where('stage', CrmLead::STAGE_CONVERTIDO)
            ->get(['created_at', 'updated_at']);

        $tempoMedioConversaoDias = $convertidos->isNotEmpty()
            ? round($convertidos->avg(fn ($l) => $l->created_at->diffInDays($l->updated_at)), 1)
            : null;

        $totalNoFunil = CrmLead::where('tenant_id', $lead->tenant_id)->count();
        $totalConvertidos = $convertidos->count();
        $taxaConversao = $totalNoFunil > 0 ? round(($totalConvertidos / $totalNoFunil) * 100, 1) : null;

        return [
            'lead' => [
                'nome' => $lead->name,
                'empresa' => $lead->company_name,
                'segmento' => $lead->segment,
                'origem' => $lead->source,
                'estagio_atual' => CrmLead::stageLabels()[$lead->stage] ?? $lead->stage,
                'dias_no_estagio_atual_aprox' => $diasNoEstagio,
                'valor_estimado' => $lead->estimated_value,
                'equipamento_interesse' => $lead->equipment_interest,
            ],
            'ultima_interacao' => $lastInteraction ? [
                'data' => $lastInteraction->contact_date?->format('d/m/Y'),
                'dias_desde_entao' => $diasSemContato,
                'resumo' => $lastInteraction->summary,
                'proximo_followup_agendado' => $lastInteraction->next_followup_date?->format('d/m/Y'),
            ] : null,
            'baseline_empresa' => [
                'taxa_media_conversao_pct' => $taxaConversao,
                'tempo_medio_conversao_dias' => $tempoMedioConversaoDias,
            ],
        ];
    }
}
