<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Client;
use App\Models\MaintenanceOrder;

/**
 * Teste pedido pelo usuário 29/09/2026: ler a foto de uma Ordem de Serviço em
 * papel (de terceiros, layout variável, inclusive manuscrita) via Claude
 * (visão) e devolver os campos estruturados pra pré-preencher o formulário
 * de criação de OS -- mesmo padrão de content-blocks base64 já usado por
 * App\Services\EquipmentDamageDiagnosisService, reaproveitando o client
 * compartilhado (App\Services\AnthropicApiClient).
 *
 * Recebe a foto como data URL (ex: "data:image/jpeg;base64,...") -- mesmo
 * formato que App\Forms\Components\CameraCapture já guarda no estado do
 * Livewire, sem precisar de upload em disco.
 */
class OsPhotoOcrService
{
    public function __construct(private AnthropicApiClient $client) {}

    /**
     * @return array{ok: bool, data: ?array<string, mixed>, error: ?string}
     */
    public function extract(string $photoDataUrl): array
    {
        [$mimeType, $base64] = $this->parseDataUrl($photoDataUrl);

        if (! $base64) {
            return ['ok' => false, 'data' => null, 'error' => 'Foto inválida.'];
        }

        $result = $this->client->send($this->systemPrompt(), [
            ['type' => 'text', 'text' => 'Leia esta foto de Ordem de Serviço e extraia os dados conforme o formato pedido.'],
            ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $base64]],
        ]);

        if (! $result['ok']) {
            return ['ok' => false, 'data' => null, 'error' => $result['error']];
        }

        $parsed = $this->client->parseJson($result['text']);

        if ($parsed === null) {
            return ['ok' => false, 'data' => null, 'error' => 'A resposta da IA não veio em um formato reconhecível.'];
        }

        return ['ok' => true, 'data' => $parsed, 'error' => null];
    }

    /**
     * Casa o JSON extraído (texto livre lido na foto) contra o cadastro real
     * do tenant -- separado de extract() de propósito, pra dar pra testar a
     * lógica de correspondência sem precisar de Filament\Forms\Get/Set (que
     * só existem dentro de um form Livewire montado). Ativo/Cliente casam
     * por LIKE parcial; Técnico casa contra a lista de opções já filtrada
     * por tenant que o Resource já monta (technicianOptionsByWorkload()),
     * pra não duplicar aquela query aqui.
     *
     * @param  array<string, mixed>  $data  retorno de extract()['data']
     * @param  array<string, string>  $technicianOptions  [user_id => label], já escopado por tenant
     * @return array{fields: array<string, mixed>, matched: array<int, string>, notFound: array<int, string>}
     */
    public function resolveFields(array $data, ?string $tenantId, array $technicianOptions): array
    {
        $fields = [];
        $matched = [];
        $notFound = [];

        if (! empty($data['asset_text']) && $tenantId) {
            $termo = $data['asset_text'];
            $query = Asset::where('tenant_id', $tenantId);

            foreach ($this->words($termo) as $word) {
                $query->where(function ($q) use ($word) {
                    $q->where('name', 'like', "%{$word}%")
                        ->orWhere('patrimonio', 'like', "%{$word}%")
                        ->orWhere('tag', 'like', "%{$word}%");
                });
            }

            $asset = $query->first();

            if ($asset) {
                $fields['asset_id'] = $asset->id;
                $fields['horimetro_anterior'] = $asset->last_horimetro ?? 0;
                $matched[] = "Ativo: {$asset->name}";
            } else {
                $notFound[] = "Ativo (lido: \"{$termo}\")";
            }
        }

        if (! empty($data['client_text']) && $tenantId) {
            $termo = $data['client_text'];
            $query = Client::where('tenant_id', $tenantId);

            foreach ($this->words($termo) as $word) {
                $query->where('name', 'like', "%{$word}%");
            }

            $client = $query->first();

            if ($client) {
                $fields['client_id'] = $client->id;
                $matched[] = "Cliente: {$client->name}";
            } else {
                $notFound[] = "Cliente (lido: \"{$termo}\")";
            }
        }

        if (! empty($data['technician_text'])) {
            $needle = mb_strtolower($data['technician_text']);
            $achou = false;

            foreach ($technicianOptions as $id => $label) {
                if (str_contains(mb_strtolower($label), $needle)) {
                    $fields['technician_id'] = $id;
                    $matched[] = "Técnico: {$label}";
                    $achou = true;

                    break;
                }
            }

            if (! $achou) {
                $notFound[] = "Técnico (lido: \"{$data['technician_text']}\")";
            }
        }

        $tiposValidos = [MaintenanceOrder::TYPE_PREVENTIVE, MaintenanceOrder::TYPE_CORRECTIVE, MaintenanceOrder::TYPE_TROCA];

        if (! empty($data['maintenance_type']) && in_array($data['maintenance_type'], $tiposValidos, true)) {
            $fields['maintenance_type'] = $data['maintenance_type'];
            $matched[] = "Tipo: {$data['maintenance_type']}";
        }

        if (! empty($data['description'])) {
            $fields['description'] = $data['description'];
            $matched[] = 'Descrição';
        }

        if (! empty($data['started_at'])) {
            $fields['started_at'] = $data['started_at'];
        }

        if (! empty($data['finished_at'])) {
            $fields['finished_at'] = $data['finished_at'];
        }

        if (isset($data['labor_cost']) && is_numeric($data['labor_cost'])) {
            $fields['labor_cost'] = $data['labor_cost'];
            $matched[] = 'Custo de mão de obra';
        }

        if (isset($data['material_cost']) && is_numeric($data['material_cost'])) {
            $fields['material_cost'] = $data['material_cost'];
            $matched[] = 'Custo de material';
        }

        if (! empty($data['checklist_notes'])) {
            $fields['technical_notes'] = $data['checklist_notes'];
            $matched[] = 'Notas técnicas';
        }

        if (array_key_exists('has_client_signature', $data)) {
            $matched[] = $data['has_client_signature']
                ? 'Assinatura do cliente detectada no papel (assine novamente na aba Assinaturas)'
                : 'Nenhuma assinatura do cliente detectada no papel';
        }

        return ['fields' => $fields, 'matched' => $matched, 'notFound' => $notFound];
    }

    /**
     * Casa por PALAVRA (AND entre elas), não pela frase inteira -- texto
     * "lido na foto" pela IA raramente bate com o nome cadastrado
     * caractere-a-caractere (ex: "Escavadeira CAT 320" != "Escavadeira
     * Hidráulica CAT 320" como substring única, mas todas as 3 palavras
     * aparecem no nome real).
     *
     * @return array<int, string>
     */
    private function words(string $text): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim($text)) ?: []));
    }

    /**
     * @return array{0: ?string, 1: ?string} [mime_type, base64]
     */
    private function parseDataUrl(string $dataUrl): array
    {
        if (! preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', $dataUrl, $matches)) {
            return [null, null];
        }

        return [$matches[1], $matches[2]];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            Você lê fotos de Ordens de Serviço em papel, de qualquer origem
            (formulário impresso de terceiros ou anotação manuscrita), tiradas em
            campo por um técnico -- a foto pode ter qualidade ruim, o documento
            pode estar amassado/manchado, e a caligrafia pode ser difícil de ler.

            Extraia apenas o que conseguir ler com confiança razoável e responda
            APENAS com um objeto JSON válido, sem markdown, sem crases, sem texto
            antes ou depois, no formato exato:

            {
              "maintenance_type": "Preventiva" ou "Corretiva" ou "Troca" ou null,
              "description": "string (descrição do serviço/problema relatado) ou null",
              "started_at": "AAAA-MM-DD ou null",
              "finished_at": "AAAA-MM-DD ou null",
              "labor_cost": number ou null,
              "material_cost": number ou null,
              "has_client_signature": true ou false,
              "checklist_notes": "string (itens de checklist/inspeção anotados no papel) ou null",
              "asset_text": "string (texto que identifica o ativo/equipamento/patrimônio, exatamente como escrito) ou null",
              "client_text": "string (texto que identifica o cliente/empresa, exatamente como escrito) ou null",
              "technician_text": "string (nome do técnico responsável, exatamente como escrito) ou null"
            }

            Nunca invente dados que não estejam visíveis na foto -- use null pro
            que não conseguir ler com confiança. Se só o dia/mês da data
            aparecer, sem ano, assuma o ano atual. Valores monetários apenas o
            número (sem "R$", sem separador de milhar).
            PROMPT;
    }
}
