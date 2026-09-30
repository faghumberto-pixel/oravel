<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Client;
use App\Models\MaintenanceOrder;
use App\Models\Material;
use Illuminate\Support\Str;

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
     * O estado do CameraCapture no form é um array ({image: data URL,
     * latitude, ...}), não a string em si -- passar direto pra extract()
     * dava TypeError, que o Livewire em produção disfarça de 419 "página
     * expirada". Aceita o array ou uma data URL pura.
     */
    public function dataUrlFromState(mixed $state): ?string
    {
        if (is_array($state)) {
            $state = $state['image'] ?? null;
        }

        return is_string($state) && $state !== '' ? $state : null;
    }

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
                    $q->whereRaw('LOWER(name) LIKE ?', [$this->like($word)])
                        ->orWhereRaw('LOWER(patrimonio) LIKE ?', [$this->like($word)])
                        ->orWhereRaw('LOWER(tag) LIKE ?', [$this->like($word)]);
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
                $query->whereRaw('LOWER(name) LIKE ?', [$this->like($word)]);
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

        foreach (['started_at', 'finished_at'] as $dateField) {
            $date = $this->validDate($data[$dateField] ?? null);

            if ($date) {
                $fields[$dateField] = $date;
            } elseif (! empty($data[$dateField])) {
                $notFound[] = "Data (lida: \"{$data[$dateField]}\")";
            }
        }

        if (isset($data['labor_cost']) && is_numeric($data['labor_cost'])) {
            $fields['labor_cost'] = $data['labor_cost'];
            $matched[] = 'Custo de mão de obra';
        }

        if (isset($data['material_cost']) && is_numeric($data['material_cost'])) {
            $fields['material_cost'] = $data['material_cost'];
            $matched[] = 'Custo de material';
        }

        $materials = $this->resolveParts($data['parts'] ?? [], $tenantId);

        if ($materials) {
            $fields['materials'] = $materials;
            $livres = collect($materials)->whereNull('material_id')->count();
            $matched[] = 'Peças/Materiais: '.count($materials).($livres ? " ({$livres} sem cadastro, como texto livre -- revisar)" : '');
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
     * Tabela de peças/materiais do papel -> linhas do repeater 'materials'
     * (MaintenanceOrderMaterial). Casa contra o catálogo Material do tenant
     * por palavra; o que não casar entra como linha de texto livre (name, sem
     * material_id -- a coluna é nullable, mesmo formato do histórico legado),
     * pra não perder o dado lido. Materiais que exigem nº de série ficam como
     * texto livre: o form não tem campo de série e a gravação estouraria.
     *
     * @return array<string, array<string, mixed>>
     */
    private function resolveParts(mixed $parts, ?string $tenantId): array
    {
        if (! is_array($parts)) {
            return [];
        }

        $lines = [];

        foreach ($parts as $part) {
            $description = is_array($part) ? trim((string) ($part['description'] ?? '')) : '';

            if ($description === '') {
                continue;
            }

            $quantity = isset($part['quantity']) && is_numeric($part['quantity']) && $part['quantity'] > 0 ? $part['quantity'] : 1;
            $unitPrice = isset($part['unit_price']) && is_numeric($part['unit_price']) ? $part['unit_price'] : null;

            $material = null;

            if ($tenantId) {
                $query = Material::where('tenant_id', $tenantId)
                    ->where(fn ($q) => $q->where('requires_serial_number', false)->orWhereNull('requires_serial_number'));

                foreach ($this->words($description) as $word) {
                    $query->whereRaw('LOWER(name) LIKE ?', [$this->like($word)]);
                }

                $material = $query->first();
            }

            $lines[(string) Str::uuid()] = [
                'material_id' => $material?->id,
                'name' => $material ? null : $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ];
        }

        return $lines;
    }

    /**
     * LIKE sem diferenciar maiúsculas/minúsculas (Postgres: LIKE puro diferencia,
     * e texto lido na foto raramente bate a caixa do cadastro -- "óleo" vs "Óleo").
     * Lowercase feito em PHP (mb_) pra acentos não dependerem do locale do banco.
     */
    private function like(string $word): string
    {
        return '%'.mb_strtolower($word).'%';
    }

    /**
     * Aceita só AAAA-MM-DD de data real (rejeita "2026-02-31", "30/09/2026", etc.).
     */
    private function validDate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
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
              "parts": [ {"description": "string (peça/material como escrito)", "quantity": number, "unit_price": number ou null} ] ou [] (tabela de peças/materiais aplicados, uma linha por item),
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
