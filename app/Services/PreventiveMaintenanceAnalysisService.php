<?php

namespace App\Services;

use App\Models\AIAnalysis;
use App\Models\Asset;
use App\Models\HorimeterReading;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use Illuminate\Support\Carbon;

/**
 * Analise de planos preventivos via Claude API. Mesmo padrao hibrido dos
 * outros servicos: numeros calculados de forma deterministica em PHP
 * (nunca confiados a IA), que a IA so' interpreta/prioriza em texto.
 *
 * Tres blocos de dado, nenhum calculado do zero -- reaproveita o que ja
 * existe:
 * - Atrasados: MaintenancePlan::dueStatusForAsset() (ja calcula tudo),
 *   mesma estrutura de loop de App\Console\Commands\CheckMaintenanceDueAlerts.
 * - Quebra pos-preventiva: nao existia em lugar nenhum -- construido aqui
 *   (ultima Preventiva concluida -> proxima Corretiva depois dela).
 * - MTBF simplificado: mesmo intervalo em dias corridos, enriquecido com
 *   horimetro quando ha leitura disponivel (nunca assume 0 quando nao ha).
 */
class PreventiveMaintenanceAnalysisService
{
    private const LIMITE_ITENS_POR_LISTA = 15;

    public function __construct(private AnthropicApiClient $client) {}

    public function analyzeTenant(string $tenantId, string $userId, int $thresholdQuebraDias = 30): AIAnalysis
    {
        $context = $this->buildContext($tenantId, $thresholdQuebraDias);

        $analysis = AIAnalysis::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => AIAnalysis::TYPE_PLANO_PREVENTIVO,
            'context' => $context,
            'status' => AIAnalysis::STATUS_PENDENTE,
        ]);

        if (empty($context['equipamentos_atrasados']) && empty($context['quebras_pos_preventiva']) && empty($context['mtbf_por_ativo'])) {
            $analysis->update([
                'status' => AIAnalysis::STATUS_FALHOU,
                'error' => 'Nenhum plano vencido, quebra pós-preventiva ou histórico de manutenção encontrado — nada a analisar no momento.',
            ]);

            return $analysis;
        }

        $result = $this->client->send(
            $this->systemPrompt(),
            "Situação dos planos preventivos:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
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

        $analysis->update([
            'status' => AIAnalysis::STATUS_CONCLUIDA,
            'response' => [
                ...$parsed,
                'equipamentos_atrasados' => $context['equipamentos_atrasados'],
                'quebras_pos_preventiva' => $context['quebras_pos_preventiva'],
                'mtbf_por_ativo' => $context['mtbf_por_ativo'],
                'threshold_quebra_dias' => $thresholdQuebraDias,
            ],
        ]);

        return $analysis;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            Você é um gestor de manutenção preventiva de frota e equipamentos
            pesados. Analise os dados recebidos (equipamentos com planos de
            preventiva vencidos, casos de equipamento que quebrou pouco tempo
            depois de uma preventiva, e o tempo médio que cada equipamento
            trabalhou sem quebrar) e responda APENAS com um objeto JSON
            válido, sem markdown, sem crases, sem texto antes ou depois, no
            formato exato:

            {
              "resumo_geral": "string",
              "equipamentos_criticos_comentario": "string",
              "padroes_quebra_pos_preventiva": "string",
              "recomendacoes": ["string", "string"]
            }

            Use SOMENTE os números fornecidos no contexto — nunca invente ou
            estime horas, dias ou quantidades. Priorize os equipamentos mais
            atrasados, e comente se os casos de "quebra logo após a
            preventiva" sugerem um problema na execução do serviço (peça
            errada, mão de obra) em vez de um problema no intervalo do plano
            em si.
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(string $tenantId, int $thresholdQuebraDias): array
    {
        return [
            'threshold_quebra_dias' => $thresholdQuebraDias,
            'equipamentos_atrasados' => $this->equipamentosAtrasados($tenantId),
            'quebras_pos_preventiva' => $this->quebrasPosPreventiva($tenantId, $thresholdQuebraDias),
            'mtbf_por_ativo' => $this->mtbfPorAtivo($tenantId),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function equipamentosAtrasados(string $tenantId): array
    {
        $assets = Asset::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['aguardando_triagem'])
            ->get();

        if ($assets->isEmpty()) {
            return [];
        }

        $todosPlanos = MaintenancePlan::where('tenant_id', $tenantId)->get();
        $atrasados = [];

        foreach ($assets as $asset) {
            $planosDoAtivo = MaintenancePlan::applicableFor($asset, $todosPlanos);

            foreach ($planosDoAtivo as $plano) {
                $status = $plano->dueStatusForAsset($asset);

                if (! $status['is_overdue']) {
                    continue;
                }

                $atrasados[] = [
                    'asset_id' => $asset->id,
                    'nome' => $asset->name,
                    'patrimonio' => $asset->patrimonio,
                    'plano' => $plano->name,
                    'overdue_hours' => $status['overdue_hours'],
                    'overdue_days' => $status['overdue_days'],
                ];
            }
        }

        return collect($atrasados)
            ->sortByDesc(fn (array $linha) => $linha['overdue_hours'] + $linha['overdue_days'])
            ->take(self::LIMITE_ITENS_POR_LISTA)
            ->values()
            ->all();
    }

    /**
     * Ultima Preventiva concluida de cada Ativo -> proxima Corretiva aberta
     * depois dela. Nao existia essa query em lugar nenhum do sistema.
     *
     * @return array<int, array<string, mixed>>
     */
    private function quebrasPosPreventiva(string $tenantId, int $thresholdQuebraDias): array
    {
        $preventivas = MaintenanceOrder::query()
            ->where('tenant_id', $tenantId)
            ->where('maintenance_type', MaintenanceOrder::TYPE_PREVENTIVE)
            ->whereIn('status', ['Concluída', 'Completado'])
            ->whereNotNull('finished_at')
            ->whereNotNull('asset_id')
            ->with('asset')
            ->orderByDesc('finished_at')
            ->get()
            ->unique('asset_id'); // mais recente por ativo (ja ordenado desc)

        if ($preventivas->isEmpty()) {
            return [];
        }

        $assetIds = $preventivas->pluck('asset_id');

        $corretivasPorAsset = MaintenanceOrder::query()
            ->whereIn('asset_id', $assetIds)
            ->where('maintenance_type', MaintenanceOrder::TYPE_CORRECTIVE)
            ->orderBy('created_at')
            ->get()
            ->groupBy('asset_id');

        $resultado = [];

        foreach ($preventivas as $preventiva) {
            $proximaCorretiva = $corretivasPorAsset->get($preventiva->asset_id, collect())
                ->first(fn (MaintenanceOrder $c) => $c->created_at && $c->created_at->greaterThan($preventiva->finished_at));

            if (! $proximaCorretiva) {
                continue;
            }

            $diasAteQuebra = (int) $preventiva->finished_at->diffInDays($proximaCorretiva->created_at, true);

            $resultado[] = [
                'asset_id' => $preventiva->asset_id,
                'nome' => $preventiva->asset?->name,
                'patrimonio' => $preventiva->asset?->patrimonio,
                'dias_ate_quebra' => $diasAteQuebra,
                'quebrou_logo_apos' => $diasAteQuebra < $thresholdQuebraDias,
            ];
        }

        return collect($resultado)
            ->sortBy('dias_ate_quebra')
            ->take(self::LIMITE_ITENS_POR_LISTA)
            ->values()
            ->all();
    }

    /**
     * MTBF simplificado: intervalo entre uma OS concluida (Preventiva ou
     * Corretiva) e a proxima Corretiva do mesmo ativo, em dias corridos +
     * horas trabalhadas quando ha leitura de horimetro cobrindo as duas
     * pontas (nunca assume 0 quando nao ha leitura).
     *
     * @return array<int, array<string, mixed>>
     */
    private function mtbfPorAtivo(string $tenantId): array
    {
        $concluidas = MaintenanceOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['Concluída', 'Completado'])
            ->whereNotNull('finished_at')
            ->whereNotNull('asset_id')
            ->with('asset')
            ->orderBy('finished_at')
            ->get();

        if ($concluidas->isEmpty()) {
            return [];
        }

        $assetIds = $concluidas->pluck('asset_id')->unique();

        $corretivasPorAsset = MaintenanceOrder::query()
            ->whereIn('asset_id', $assetIds)
            ->where('maintenance_type', MaintenanceOrder::TYPE_CORRECTIVE)
            ->orderBy('created_at')
            ->get()
            ->groupBy('asset_id');

        $intervalosPorAsset = [];

        foreach ($concluidas->groupBy('asset_id') as $assetId => $concluidasDoAtivo) {
            $corretivasDoAtivo = $corretivasPorAsset->get($assetId, collect());

            foreach ($concluidasDoAtivo as $osAnterior) {
                $proximaCorretiva = $corretivasDoAtivo->first(fn (MaintenanceOrder $c) => $c->id !== $osAnterior->id
                    && $c->created_at
                    && $c->created_at->greaterThan($osAnterior->finished_at)
                );

                if (! $proximaCorretiva) {
                    continue;
                }

                $dias = (int) $osAnterior->finished_at->diffInDays($proximaCorretiva->created_at, true);
                $horas = $this->horasTrabalhadasEntre($assetId, $osAnterior->finished_at, $proximaCorretiva->created_at);

                $intervalosPorAsset[$assetId] ??= [
                    'asset_id' => $assetId,
                    'nome' => $osAnterior->asset?->name,
                    'dias_sem_quebrar' => [],
                    'horas_sem_quebrar' => [],
                ];
                $intervalosPorAsset[$assetId]['dias_sem_quebrar'][] = $dias;

                if ($horas !== null) {
                    $intervalosPorAsset[$assetId]['horas_sem_quebrar'][] = $horas;
                }
            }
        }

        return collect($intervalosPorAsset)
            ->filter(fn (array $linha) => ! empty($linha['dias_sem_quebrar']))
            ->map(fn (array $linha) => [
                'asset_id' => $linha['asset_id'],
                'nome' => $linha['nome'],
                'media_dias_sem_quebrar' => round(array_sum($linha['dias_sem_quebrar']) / count($linha['dias_sem_quebrar']), 1),
                'media_horas_trabalhadas' => empty($linha['horas_sem_quebrar'])
                    ? null
                    : round(array_sum($linha['horas_sem_quebrar']) / count($linha['horas_sem_quebrar']), 1),
            ])
            ->values()
            ->all();
    }

    private function horasTrabalhadasEntre(string $assetId, Carbon $inicio, Carbon $fim): ?float
    {
        $leituraInicio = HorimeterReading::query()
            ->latestForAsset($assetId)
            ->where('recorded_at', '<=', $inicio)
            ->first();

        $leituraFim = HorimeterReading::query()
            ->latestForAsset($assetId)
            ->where('recorded_at', '<=', $fim)
            ->first();

        if (! $leituraInicio || ! $leituraFim || $leituraInicio->id === $leituraFim->id) {
            return null;
        }

        return round((float) $leituraFim->reading - (float) $leituraInicio->reading, 1);
    }
}
