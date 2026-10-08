<?php

namespace App\Services;

/**
 * Lê a imagem de uma proposta que o cliente já usa e devolve a estrutura
 * para montar o Template de Proposta: cabeçalho da empresa, termos gerais,
 * validade e os campos (título + texto) que a proposta mostra ao cliente.
 */
class PropostaTemplateImportador
{
    public function __construct(private AnthropicApiClient $client) {}

    /**
     * @return array{ok: bool, data: ?array{nome: ?string, cabecalho: ?string, termos: ?string, validade_dias: ?int, campos: array<int, array{titulo: string, texto: string}>}, error: ?string}
     */
    public function analisar(string $conteudoBinario, string $mimeType): array
    {
        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return ['ok' => false, 'data' => null, 'error' => 'Envie a proposta como imagem (JPG, PNG ou WebP).'];
        }

        $result = $this->client->send($this->systemPrompt(), [
            ['type' => 'text', 'text' => 'Leia esta proposta comercial e devolva a estrutura no formato pedido.'],
            ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => base64_encode($conteudoBinario)]],
        ], 3000);

        if (! $result['ok']) {
            return ['ok' => false, 'data' => null, 'error' => $result['error']];
        }

        $json = $this->client->parseJson($result['text']);

        if (! $json) {
            return ['ok' => false, 'data' => null, 'error' => 'Não consegui entender a imagem. Tente uma foto mais nítida.'];
        }

        return ['ok' => true, 'data' => $this->normalizar($json), 'error' => null];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{nome: ?string, cabecalho: ?string, termos: ?string, validade_dias: ?int, campos: array<int, array{titulo: string, texto: string}>}
     */
    public function normalizar(array $json): array
    {
        $campos = [];
        foreach ((array) ($json['campos'] ?? []) as $campo) {
            $titulo = trim((string) ($campo['titulo'] ?? ''));
            if ($titulo === '') {
                continue;
            }
            $campos[] = ['titulo' => mb_substr($titulo, 0, 120), 'texto' => trim((string) ($campo['texto'] ?? ''))];
        }

        $dias = isset($json['validade_dias']) && is_numeric($json['validade_dias']) ? max(1, (int) $json['validade_dias']) : null;

        return [
            'nome' => $this->texto($json['nome'] ?? null),
            'cabecalho' => $this->texto($json['cabecalho'] ?? null),
            'termos' => $this->texto($json['termos'] ?? null),
            'validade_dias' => $dias,
            'campos' => $campos,
        ];
    }

    private function texto(mixed $valor): ?string
    {
        $valor = is_string($valor) ? trim($valor) : '';

        return $valor === '' ? null : $valor;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Você lê a imagem de uma proposta comercial de locação de equipamentos e extrai a ESTRUTURA dela, para virar um modelo reutilizável.
Responda SOMENTE com JSON válido, sem markdown, neste formato:
{
  "nome": "nome curto do modelo, ex.: Proposta de locação",
  "cabecalho": "dados da empresa que emite (razão social, CNPJ, endereço, telefone, e-mail), em linhas separadas por \n",
  "termos": "condições gerais em texto corrido, ou null",
  "validade_dias": número de dias de validade da proposta, ou null,
  "campos": [{"titulo": "título da seção, ex.: Condições de pagamento", "texto": "texto padrão da seção, ou vazio se varia a cada proposta"}]
}
Regras: não copie dados do cliente destinatário, itens, quantidades nem valores (isso muda a cada proposta). Mantenha as seções na ordem da imagem. Se algo não aparecer, use null ou lista vazia. Não invente.
PROMPT;
    }
}
