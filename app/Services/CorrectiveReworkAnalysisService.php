<?php

namespace App\Services;

use App\Models\AIAnalysis;
use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrder;

/**
 * Analise de retrabalho/corretivas via Claude API. Mesmo padrao de
 * StockAnalysisService: os numeros (retrabalhos por ativo, dias entre
 * ocorrencias, causas classificadas) sao calculados de forma deterministica
 * aqui -- a IA so' interpreta/prioriza em linguagem natural por cima.
 *
 * Retrabalho aqui e' definido de forma mais estrita que
 * DesempenhoTecnico::getRetrabalhosProperty() (app/Filament/Pages/
 * DesempenhoTecnico.php): so' conta quando TANTO a OS antiga quanto a nova
 * sao Corretiva -- o pedido do usuario e' especificamente sobre corretivas
 * ("motivos das corretivas... quantas vezes fomos ao mesmo equipamento").
 * Preventiva seguida de corretiva e' um caso diferente (ver
 * PreventiveMaintenanceAnalysisService::analyzeTenant(), "quebra pos
 * preventiva").
 */
class CorrectiveReworkAnalysisService
{
    private const LIMITE_ITENS_POR_LISTA = 15;

    private const LIMITE_AMOSTRA_TEXTO_LIVRE = 25;

    public function __construct(private AnthropicApiClient $client) {}

    public function analyzeTenant(string $tenantId, string $userId, int $janelaDias = 30): AIAnalysis
    {
        $context = $this->buildContext($tenantId, $janelaDias);

        $analysis = AIAnalysis::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => AIAnalysis::TYPE_RETRABALHO,
            'context' => $context,
            'status' => AIAnalysis::STATUS_PENDENTE,
        ]);

        if (empty($context['ranking_retrabalho'])) {
            $analysis->update([
                'status' => AIAnalysis::STATUS_FALHOU,
                'error' => "Nenhum retrabalho detectado na janela de {$janelaDias} dias — nada a analisar no momento.",
            ]);

            return $analysis;
        }

        $result = $this->client->send(
            $this->systemPrompt(),
            "Retrabalho de manutenção corretiva:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
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
        // da IA so' soma a interpretacao/priorizacao em texto por cima.
        $analysis->update([
            'status' => AIAnalysis::STATUS_CONCLUIDA,
            'response' => [
                ...$parsed,
                'ranking_retrabalho' => $context['ranking_retrabalho'],
                'causas_classificadas' => $context['causas_classificadas'],
                'total_retrabalhos' => $context['total_retrabalhos'],
                'janela_dias' => $janelaDias,
            ],
        ]);

        return $analysis;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            Você é um gestor de manutenção de frota e equipamentos pesados,
            especializado em análise de causa raiz. Analise os dados de
            retrabalho recebidos (ativos que receberam mais de uma OS
            corretiva dentro de uma janela de dias, contagem de causas
            classificadas e uma amostra de descrições de problema em texto
            livre) e responda APENAS com um objeto JSON válido, sem markdown,
            sem crases, sem texto antes ou depois, no formato exato:

            {
              "resumo_geral": "string",
              "principais_causas_interpretadas": "string",
              "equipamentos_criticos_comentario": "string",
              "recomendacoes": ["string", "string"]
            }

            Use SOMENTE os números fornecidos no contexto — nunca invente ou
            estime contagens, datas ou quantidades. Seu papel é interpretar e
            priorizar em texto: cruze a contagem de causas classificadas com
            os padrões percebidos na amostra de texto livre (ex.: várias
            descrições sem causa cadastrada mencionando o mesmo sintoma
            sugerem uma causa raiz recorrente), e comente os ativos no topo
            do ranking de retrabalho.
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(string $tenantId, int $janelaDias): array
    {
        $concluidas = MaintenanceOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('maintenance_type', MaintenanceOrder::TYPE_CORRECTIVE)
            ->whereIn('status', ['Concluída', 'Completado'])
            ->whereNotNull('finished_at')
            ->whereNotNull('asset_id')
            ->orderBy('finished_at')
            ->get(['id', 'asset_id', 'finished_at']);

        if ($concluidas->isEmpty()) {
            return [
                'janela_dias' => $janelaDias,
                'total_retrabalhos' => 0,
                'ranking_retrabalho' => [],
                'causas_classificadas' => [],
            ];
        }

        $assetIds = $concluidas->pluck('asset_id')->unique();

        $corretivasPorAsset = MaintenanceOrder::query()
            ->whereIn('asset_id', $assetIds)
            ->where('maintenance_type', MaintenanceOrder::TYPE_CORRECTIVE)
            ->with(['asset', 'reportedProblem'])
            ->orderBy('created_at')
            ->get()
            ->groupBy('asset_id');

        $danosPorOs = EquipmentDamage::query()
            ->whereIn('maintenance_order_id', $corretivasPorAsset->flatten()->pluck('id'))
            ->get()
            ->keyBy('maintenance_order_id');

        $retrabalhosPorAsset = [];
        $amostraTextoLivre = [];
        $causasContagem = [];

        foreach ($concluidas->groupBy('asset_id') as $assetId => $concluidasDoAtivo) {
            $todasDoAtivo = $corretivasPorAsset->get($assetId, collect());

            foreach ($concluidasDoAtivo as $osAntiga) {
                $limite = $osAntiga->finished_at->copy()->addDays($janelaDias);

                $proxima = $todasDoAtivo->first(fn (MaintenanceOrder $candidata) => $candidata->id !== $osAntiga->id
                    && $candidata->created_at
                    && $candidata->created_at->greaterThan($osAntiga->finished_at)
                    && $candidata->created_at->lessThanOrEqualTo($limite)
                );

                if (! $proxima) {
                    continue;
                }

                $asset = $todasDoAtivo->first()?->asset;
                $diasDepois = (int) $osAntiga->finished_at->diffInDays($proxima->created_at, true);

                $retrabalhosPorAsset[$assetId] ??= [
                    'asset_id' => $assetId,
                    'nome' => $asset?->name ?? 'Ativo removido',
                    'patrimonio' => $asset?->patrimonio,
                    'total_retrabalhos' => 0,
                    'intervalos_dias' => [],
                ];
                $retrabalhosPorAsset[$assetId]['total_retrabalhos']++;
                $retrabalhosPorAsset[$assetId]['intervalos_dias'][] = $diasDepois;

                $dano = $danosPorOs->get($proxima->id);

                if ($dano) {
                    $causasContagem[$dano->cause] = ($causasContagem[$dano->cause] ?? 0) + 1;
                } elseif (count($amostraTextoLivre) < self::LIMITE_AMOSTRA_TEXTO_LIVRE) {
                    $descricao = $proxima->reportedProblem?->description ?? $proxima->description;

                    if (filled($descricao)) {
                        $amostraTextoLivre[] = [
                            'ativo' => $asset?->name,
                            'descricao' => $descricao,
                        ];
                    }
                }
            }
        }

        $ranking = collect($retrabalhosPorAsset)
            ->map(fn (array $linha) => [
                ...$linha,
                'dias_medio_entre_ocorrencias' => round(array_sum($linha['intervalos_dias']) / count($linha['intervalos_dias']), 1),
            ])
            ->sortByDesc('total_retrabalhos')
            ->take(self::LIMITE_ITENS_POR_LISTA)
            ->values()
            ->all();

        $totalRetrabalhos = collect($retrabalhosPorAsset)->sum('total_retrabalhos');

        return [
            'janela_dias' => $janelaDias,
            'total_retrabalhos' => $totalRetrabalhos,
            'ranking_retrabalho' => $ranking,
            'causas_classificadas' => collect($causasContagem)
                ->map(fn ($total, $cause) => ['causa' => EquipmentDamage::causeLabels()[$cause] ?? $cause, 'total' => $total])
                ->values()
                ->all(),
            'amostra_descricoes_sem_causa_classificada' => $amostraTextoLivre,
        ];
    }
}
