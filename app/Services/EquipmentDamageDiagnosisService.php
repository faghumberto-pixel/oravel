<?php

namespace App\Services;

use App\Models\AIAnalysis;
use App\Models\EquipmentDamage;
use Illuminate\Support\Facades\Log;

/**
 * Diagnostico de avaria via Claude (Anthropic Messages API), com visao
 * (fotos da MediaLibrary). Sem ANTHROPIC_API_KEY configurada, analyze()
 * retorna null -- a tela de avaria continua funcionando normalmente, so'
 * fica sem a acao de IA. Mesmo padrao de degradacao do
 * App\Services\AudioTranscriptionService (OpenAI Whisper). Chamada real
 * feita via App\Services\AnthropicApiClient (compartilhado com os outros
 * servicos de IA).
 */
class EquipmentDamageDiagnosisService
{
    private const MAX_PHOTOS = 3;

    public function __construct(private AnthropicApiClient $client) {}

    public function analyze(EquipmentDamage $damage, string $userId): AIAnalysis
    {
        $context = $this->buildContext($damage);

        $analysis = AIAnalysis::create([
            'tenant_id' => $damage->tenant_id,
            'user_id' => $userId,
            'type' => AIAnalysis::TYPE_AVARIA,
            'equipment_damage_id' => $damage->id,
            'context' => $context,
            'status' => AIAnalysis::STATUS_PENDENTE,
        ]);

        $result = $this->client->send($this->systemPrompt(), $this->buildMessageContent($context, $damage));

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
            Você é um técnico mecânico com 20 anos de experiência em manutenção de
            máquinas pesadas (geradores, plataformas elevatórias, guindastes,
            compressores). Um técnico de campo relatou uma avaria e anexou uma foto
            (quando disponível). Analise o contexto e responda APENAS com um objeto
            JSON válido, sem markdown, sem crases, sem texto antes ou depois, no
            seguinte formato exato:

            {
              "diagnostico_provavel": "string",
              "causa_raiz": "string",
              "acoes_corretivas": ["string", "string"],
              "tempo_estimado_reparo": "string (ex: 6-8 horas)",
              "pecas_necessarias": ["string", "string"],
              "custo_estimado_min": number,
              "custo_estimado_max": number,
              "equipamento_substituto_sugerido": "string ou null"
            }

            Valores monetários em reais (BRL), apenas o número (sem "R$"). Se não
            houver informação suficiente para algum campo, use uma string vazia ou
            null, mas nunca omita a chave.
            PROMPT;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMessageContent(array $context, EquipmentDamage $damage): array
    {
        $content = [
            [
                'type' => 'text',
                'text' => "Dados da avaria:\n".json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ],
        ];

        foreach ($this->photosAsBase64($damage) as $photo) {
            $content[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $photo['mime'],
                    'data' => $photo['base64'],
                ],
            ];
        }

        return $content;
    }

    /**
     * @return array<int, array{mime: string, base64: string}>
     */
    private function photosAsBase64(EquipmentDamage $damage): array
    {
        $photos = [];

        foreach ($damage->getMedia('photos')->take(self::MAX_PHOTOS) as $media) {
            try {
                $path = $media->getPath();

                if (! is_file($path)) {
                    continue;
                }

                $photos[] = [
                    'mime' => $media->mime_type,
                    'base64' => base64_encode(file_get_contents($path)),
                ];
            } catch (\Throwable $e) {
                Log::warning('Falha ao carregar foto da avaria pra IA', [
                    'equipment_damage_id' => $damage->id,
                    'media_id' => $media->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $photos;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(EquipmentDamage $damage): array
    {
        $asset = $damage->asset;

        $recentMaintenance = $asset
            ? $asset->maintenanceOrders()
                ->latest()
                ->take(3)
                ->get(['os_number', 'maintenance_type', 'description', 'technical_notes', 'status', 'created_at'])
                ->map(fn ($order) => [
                    'os_number' => $order->os_number,
                    'tipo' => $order->maintenance_type,
                    'descricao' => $order->description,
                    'observacoes_tecnicas' => $order->technical_notes,
                    'status' => $order->status,
                    'data' => $order->created_at?->format('d/m/Y'),
                ])
                ->all()
            : [];

        return [
            'ativo' => $asset ? [
                'patrimonio' => $asset->patrimonio,
                'nome' => $asset->name,
                'categoria' => $asset->category?->name ?? $asset->asset_category,
                'horimetro_atual' => $asset->horimetro_atual,
            ] : null,
            'avaria' => [
                'severidade' => $damage->severity,
                'tipo' => $damage->damage_type ? (EquipmentDamage::damageTypeLabels()[$damage->damage_type] ?? $damage->damage_type) : null,
                'causa_reportada' => $damage->cause ? (EquipmentDamage::causeLabels()[$damage->cause] ?? $damage->cause) : null,
                'descricao_tecnico' => $damage->description,
            ],
            'historico_manutencao_recente' => $recentMaintenance,
        ];
    }
}
