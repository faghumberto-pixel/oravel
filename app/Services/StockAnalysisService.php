<?php

namespace App\Services;

use App\Models\AIAnalysis;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;

/**
 * Analise de saude do estoque via Claude API. Mesmo padrao de
 * LogisticsRouteAnalysisService: os numeros (materiais criticos, consumo
 * medio, dias estimados ate esgotar, materiais parados) sao calculados de
 * forma deterministica aqui -- a IA so' escreve o resumo/priorizacao em
 * linguagem natural por cima. Chamada real feita via
 * App\Services\AnthropicApiClient (compartilhado com os outros servicos
 * de IA).
 */
class StockAnalysisService
{
    private const JANELA_CONSUMO_DIAS = 90;

    private const DIAS_PARA_ESTOQUE_PARADO = 90;

    private const LIMITE_ITENS_POR_LISTA = 15;

    public function __construct(private AnthropicApiClient $client) {}

    public function analyzeTenant(string $tenantId, string $userId): AIAnalysis
    {
        $context = $this->buildContext($tenantId);

        $analysis = AIAnalysis::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => AIAnalysis::TYPE_ESTOQUE,
            'context' => $context,
            'status' => AIAnalysis::STATUS_PENDENTE,
        ]);

        if (empty($context['materiais_criticos']) && empty($context['materiais_parados'])) {
            $analysis->update([
                'status' => AIAnalysis::STATUS_FALHOU,
                'error' => 'Nenhum material abaixo do estoque mínimo ou parado encontrado — nada a analisar no momento.',
            ]);

            return $analysis;
        }

        $result = $this->client->send(
            $this->systemPrompt(),
            "Situação do estoque:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
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

        // Numeros calculados (context) sao a fonte de verdade; a resposta
        // da IA so' soma o resumo/priorizacao em texto por cima.
        $analysis->update([
            'status' => AIAnalysis::STATUS_CONCLUIDA,
            'response' => [
                ...$parsed,
                'materiais_criticos' => $context['materiais_criticos'],
                'materiais_parados' => $context['materiais_parados'],
            ],
        ]);

        return $analysis;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            Você é um gestor de suprimentos especializado em manutenção de frota e
            equipamentos pesados. Analise a situação de estoque recebida (materiais
            abaixo do mínimo, consumo médio mensal, dias estimados até esgotar, e
            materiais parados sem giro) e responda APENAS com um objeto JSON válido,
            sem markdown, sem crases, sem texto antes ou depois, no formato exato:

            {
              "resumo_geral": "string",
              "prioridades_compra": ["string", "string"],
              "recomendacoes_estoque_parado": ["string", "string"]
            }

            Priorize por urgência real (dias estimados até esgotar, não apenas o
            quanto falta), e sinalize se um pedido de compra já em aberto cobre o
            risco antes de recomendar comprar de novo.
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(string $tenantId): array
    {
        $materiais = Material::where('tenant_id', $tenantId)->with('supplier')->get();

        $criticos = $materiais
            ->filter(fn (Material $m) => $m->isLowStock())
            ->map(fn (Material $m) => $this->describeCritico($m))
            ->sortBy('dias_estimados_para_esgotar')
            ->take(self::LIMITE_ITENS_POR_LISTA)
            ->values()
            ->all();

        $parados = $materiais
            ->filter(fn (Material $m) => $this->isParado($m))
            ->map(fn (Material $m) => [
                'sku' => $m->sku,
                'nome' => $m->name,
                'estoque_atual' => (float) $m->current_stock,
                'valor_parado' => round((float) $m->current_stock * (float) $m->unit_cost, 2),
                'dias_sem_movimentacao' => $this->diasSemMovimentacao($m),
            ])
            ->sortByDesc('valor_parado')
            ->take(self::LIMITE_ITENS_POR_LISTA)
            ->values()
            ->all();

        return [
            'resumo' => [
                'total_materiais_cadastrados' => $materiais->count(),
                'total_criticos' => count($criticos),
                'total_parados' => count($parados),
                'valor_total_parado' => round(collect($parados)->sum('valor_parado'), 2),
            ],
            'materiais_criticos' => $criticos,
            'materiais_parados' => $parados,
        ];
    }

    private function describeCritico(Material $material): array
    {
        $consumoMensal = $this->consumoMedioMensal($material);
        $consumoDiario = $consumoMensal / 30;

        return [
            'sku' => $material->sku,
            'nome' => $material->name,
            'estoque_atual' => (float) $material->current_stock,
            'estoque_minimo' => (float) $material->min_stock,
            'consumo_medio_mensal' => round($consumoMensal, 1),
            'dias_estimados_para_esgotar' => $consumoDiario > 0
                ? round((float) $material->current_stock / $consumoDiario, 1)
                : null,
            'fornecedor' => $material->supplier?->name,
            'tem_pedido_compra_aberto' => $this->temPedidoCompraAberto($material),
        ];
    }

    /**
     * Media mensal de consumo (saida_consumo) nos ultimos
     * self::JANELA_CONSUMO_DIAS dias, usada pra estimar quantos dias
     * faltam ate o estoque zerar no ritmo atual.
     */
    private function consumoMedioMensal(Material $material): float
    {
        $totalConsumido = $material->stockMovements()
            ->where('type', StockMovement::TYPE_SAIDA_CONSUMO)
            ->where('created_at', '>=', now()->subDays(self::JANELA_CONSUMO_DIAS))
            ->sum('quantity');

        return ((float) $totalConsumido) / (self::JANELA_CONSUMO_DIAS / 30);
    }

    private function temPedidoCompraAberto(Material $material): bool
    {
        return $material->purchaseOrderItems()
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', [
                PurchaseOrder::STATUS_ABERTA,
                PurchaseOrder::STATUS_PARCIALMENTE_RECEBIDA,
            ]))
            ->exists();
    }

    /**
     * "Parado" = tem saldo em estoque mas nao teve nenhuma saida por
     * consumo na janela recente -- candidato a capital empatado sem uso,
     * distinto de "critico" (que e' o oposto: falta de estoque).
     */
    private function isParado(Material $material): bool
    {
        if ((float) $material->current_stock <= 0) {
            return false;
        }

        return ! $material->stockMovements()
            ->where('type', StockMovement::TYPE_SAIDA_CONSUMO)
            ->where('created_at', '>=', now()->subDays(self::DIAS_PARA_ESTOQUE_PARADO))
            ->exists();
    }

    private function diasSemMovimentacao(Material $material): ?int
    {
        $ultimaSaida = $material->stockMovements()
            ->where('type', StockMovement::TYPE_SAIDA_CONSUMO)
            ->latest('created_at')
            ->first();

        return $ultimaSaida ? now()->diffInDays($ultimaSaida->created_at) : null;
    }
}
