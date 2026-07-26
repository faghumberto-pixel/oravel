<?php

namespace App\Services;

use App\Models\AIAnalysis;
use App\Models\Depot;
use App\Models\EquipmentMovement;

/**
 * Analise de rotas do dia por veiculo: RouteOptimizationService faz a
 * matematica (Haversine + vizinho mais proximo, distancia em km e custo
 * em R$ via FleetVehicle.custo_por_km -- tudo determinístico, nao
 * inventado pela IA), e a Claude API so' escreve o resumo em linguagem
 * natural por cima dos numeros ja calculados. Mesmo padrao de degradacao
 * de EquipmentDamageDiagnosisService: sem ANTHROPIC_API_KEY, a analise
 * fica com status 'falhou' mas os numeros calculados continuam
 * disponiveis em $analysis->context. Chamada real feita via
 * App\Services\AnthropicApiClient (compartilhado com os outros servicos
 * de IA).
 */
class LogisticsRouteAnalysisService
{
    public function __construct(
        private RouteOptimizationService $router,
        private AnthropicApiClient $client,
    ) {}

    public function analyzeDate(string $tenantId, string $userId, string $date): AIAnalysis
    {
        $context = $this->buildContext($tenantId, $date);

        $analysis = AIAnalysis::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => AIAnalysis::TYPE_LOGISTICA,
            'context' => $context,
            'status' => AIAnalysis::STATUS_PENDENTE,
        ]);

        if (empty($context['rotas'])) {
            $analysis->update([
                'status' => AIAnalysis::STATUS_FALHOU,
                'error' => $context['aviso'] ?? 'Nenhuma rota com 2+ paradas geolocalizadas encontrada para essa data.',
            ]);

            return $analysis;
        }

        $result = $this->client->send(
            $this->systemPrompt(),
            "Rotas calculadas:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
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

        // Numeros calculados (context['rotas']) sao a fonte de verdade;
        // a resposta da IA so' soma o resumo em texto por cima.
        $analysis->update([
            'status' => AIAnalysis::STATUS_CONCLUIDA,
            'response' => [
                ...$parsed,
                'rotas' => $context['rotas'],
            ],
        ]);

        return $analysis;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            Você é um especialista em roteirização de frota. Você vai receber uma
            lista de rotas já calculadas (sequência atual, sequência otimizada,
            distâncias e economia em km/reais — os números já estão corretos, não
            os recalcule nem os altere). Sua tarefa é só escrever um resumo claro
            em português para o gestor de logística.

            Responda APENAS com um objeto JSON válido, sem markdown, sem crases,
            sem texto antes ou depois, no formato exato:

            {
              "resumo_geral": "string (1-2 frases sobre a economia total do dia)",
              "recomendacoes": ["string", "string"],
              "dica_pratica": "string ou null (ex: sugestão de horário de saída)"
            }
            PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(string $tenantId, string $date): array
    {
        $depot = Depot::where('tenant_id', $tenantId)->where('is_default', true)->first()
            ?? Depot::where('tenant_id', $tenantId)->whereNotNull('latitude')->first();

        if (! $depot?->hasCoordinates()) {
            return ['data' => $date, 'rotas' => [], 'aviso' => 'Nenhum pátio com coordenadas cadastrado (Pátios/Depósitos).'];
        }

        $origin = ['lat' => (float) $depot->latitude, 'lng' => (float) $depot->longitude];

        $movements = EquipmentMovement::where('tenant_id', $tenantId)
            ->whereDate('scheduled_at', $date)
            ->whereNotNull('fleet_vehicle_id')
            ->where('status', '!=', EquipmentMovement::STATUS_CONCLUIDO)
            ->with(['asset.client', 'fleetVehicle'])
            ->orderBy('scheduled_at')
            ->get()
            ->filter(fn ($m) => $m->asset?->client?->latitude !== null && $m->asset?->client?->longitude !== null);

        $rotas = [];

        foreach ($movements->groupBy('fleet_vehicle_id') as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $vehicle = $group->first()->fleetVehicle;

            $stopsAtualOrdem = $group->map(fn ($m) => [
                'id' => $m->id,
                'lat' => (float) $m->asset->client->latitude,
                'lng' => (float) $m->asset->client->longitude,
                'label' => $m->asset->client->name,
            ])->values()->all();

            $distanciaAtual = $this->router->routeDistanceKm($origin, $stopsAtualOrdem);
            $otimizado = $this->router->optimize($origin, $stopsAtualOrdem);

            $custoPorKm = (float) ($vehicle?->custo_por_km ?? 0);
            $economiaKm = round($distanciaAtual - $otimizado['total_km'], 1);

            $rotas[] = [
                'veiculo' => $vehicle?->placa ?? $vehicle?->modelo ?? 'Veículo',
                'sequencia_atual' => array_column($stopsAtualOrdem, 'label'),
                'distancia_atual_km' => $distanciaAtual,
                'sequencia_otimizada' => array_column($otimizado['order'], 'label'),
                'distancia_otimizada_km' => $otimizado['total_km'],
                'economia_km' => $economiaKm,
                'economia_estimada_reais' => $custoPorKm > 0 ? round($economiaKm * $custoPorKm, 2) : null,
            ];
        }

        return [
            'data' => $date,
            'patio_origem' => $depot->name,
            'rotas' => $rotas,
        ];
    }
}
