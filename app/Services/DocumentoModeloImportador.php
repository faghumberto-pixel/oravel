<?php

namespace App\Services;

use App\Models\DocumentoModelo;
use ZipArchive;

/**
 * Lê o documento que o cliente já usa (orçamento, proposta ou contrato; imagem, PDF ou Word) e devolve a estrutura para montar o modelo dele:
 * cabeçalho, seções ou cláusulas, termos, rodapé e validade. Dados da pessoa que recebe o documento, itens e valores NÃO entram no modelo:
 * mudam a cada documento e viram marcadores ({{cliente}}, {{valor_total}}...) quando aparecem no meio de um texto.
 */
class DocumentoModeloImportador
{
    private const IMAGENS = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private const PDF = 'application/pdf';

    private const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    /** Marcadores que o sistema preenche ao imprimir ou enviar o documento. */
    public const MARCADORES = ['{{cliente}}', '{{cnpj}}', '{{endereco_cliente}}', '{{data}}', '{{numero}}', '{{valor_total}}', '{{validade}}', '{{empresa}}'];

    public function __construct(private AnthropicApiClient $client) {}

    /**
     * @return array{ok: bool, data: ?array{nome: ?string, cabecalho: ?string, rodape: ?string, termos: ?string, validade_dias: ?int, campos: array<int, array{titulo: string, texto: string}>}, error: ?string}
     */
    public function analisar(string $conteudoBinario, string $mimeType, string $tipo): array
    {
        if (! array_key_exists($tipo, DocumentoModelo::tipos())) {
            return ['ok' => false, 'data' => null, 'error' => 'Escolha o tipo do documento.'];
        }

        $bloco = $this->blocoDoArquivo($conteudoBinario, $mimeType);
        if (is_string($bloco)) {
            return ['ok' => false, 'data' => null, 'error' => $bloco];
        }

        $result = $this->client->sendJson($this->systemPrompt($tipo), [
            ['type' => 'text', 'text' => 'Leia este documento e devolva a estrutura do modelo no formato pedido.'],
            $bloco,
        ], 6000);

        if (! $result['ok'] || ! is_array($result['data'] ?? null)) {
            return ['ok' => false, 'data' => null, 'error' => $result['error'] ?? 'Não consegui entender o arquivo. Tente um arquivo mais nítido.'];
        }

        return ['ok' => true, 'data' => $this->normalizar($result['data']), 'error' => null];
    }

    /**
     * Bloco de conteúdo para a IA, ou uma mensagem de erro (string) quando o formato não serve.
     *
     * @return array<string, mixed>|string
     */
    private function blocoDoArquivo(string $conteudo, string $mime): array|string
    {
        if (in_array($mime, self::IMAGENS, true)) {
            return ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($conteudo)]];
        }
        if ($mime === self::PDF) {
            return ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => self::PDF, 'data' => base64_encode($conteudo)]];
        }
        if ($mime === self::DOCX) {
            $texto = $this->textoDoDocx($conteudo);

            return $texto === '' ? 'Não consegui ler o texto desse arquivo Word. Tente salvar como PDF.' : ['type' => 'text', 'text' => "Conteúdo do documento:\n\n".$texto];
        }

        return 'Envie o documento como imagem (JPG, PNG, WebP), PDF ou Word (.docx).';
    }

    /** Texto de um .docx (zip com word/document.xml), um parágrafo por linha. */
    public function textoDoDocx(string $conteudo): string
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($arquivo, $conteudo);

        try {
            $zip = new ZipArchive;
            if ($zip->open($arquivo) !== true) {
                return '';
            }
            $xml = (string) $zip->getFromName('word/document.xml');
            $zip->close();
        } finally {
            @unlink($arquivo);
        }

        if ($xml === '') {
            return '';
        }

        // Fim de parágrafo e quebra de linha viram \n; o resto das tags sai; entidades voltam a ser texto.
        $xml = preg_replace(['/<\/w:p>/', '/<w:br\s*\/?>/', '/<w:tab\s*\/?>/'], ["\n", "\n", ' '], $xml);
        $texto = html_entity_decode(strip_tags((string) $xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $texto));
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{nome: ?string, cabecalho: ?string, rodape: ?string, termos: ?string, validade_dias: ?int, campos: array<int, array{titulo: string, texto: string}>}
     */
    public function normalizar(array $json): array
    {
        $campos = [];
        foreach ((array) ($json['campos'] ?? []) as $campo) {
            $titulo = trim((string) ($campo['titulo'] ?? ''));
            if ($titulo === '') {
                continue;
            }
            $campos[] = ['titulo' => mb_substr($titulo, 0, 160), 'texto' => trim((string) ($campo['texto'] ?? ''))];
        }

        $dias = isset($json['validade_dias']) && is_numeric($json['validade_dias']) ? max(1, (int) $json['validade_dias']) : null;

        return [
            'nome' => $this->texto($json['nome'] ?? null),
            'cabecalho' => $this->texto($json['cabecalho'] ?? null),
            'rodape' => $this->texto($json['rodape'] ?? null),
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

    private function systemPrompt(string $tipo): string
    {
        $documento = match ($tipo) {
            DocumentoModelo::TIPO_ORCAMENTO => 'um orçamento (de locação, serviço ou venda de equipamentos)',
            DocumentoModelo::TIPO_CONTRATO => 'um contrato (de locação de equipamentos ou prestação de serviços)',
            default => 'uma proposta comercial de locação de equipamentos',
        };
        $secoes = $tipo === DocumentoModelo::TIPO_CONTRATO
            ? 'Em "campos" coloque as CLÁUSULAS na ordem do documento: "titulo" é o número e o nome da cláusula (ex.: "CLÁUSULA 3 – DO PRAZO") e "texto" é o texto COMPLETO dela, sem resumir.'
            : 'Em "campos" coloque as seções que o cliente final lê, na ordem do documento (ex.: "Condições de pagamento"); "texto" é o texto padrão da seção, ou vazio se varia a cada documento.';
        $marcadores = implode(', ', self::MARCADORES);

        return <<<PROMPT
Você lê {$documento} e extrai a ESTRUTURA dele, para virar um modelo reutilizável.
Responda SOMENTE com JSON válido, sem markdown, neste formato:
{
  "nome": "nome curto do modelo",
  "cabecalho": "dados da empresa que emite (razão social, CNPJ, endereço, telefone, e-mail), em linhas separadas por \\n, ou null",
  "rodape": "texto do rodapé (ex.: foro, dados bancários, observações finais), ou null",
  "termos": "condições gerais em texto corrido, ou null",
  "validade_dias": número de dias de validade, ou null,
  "campos": [{"titulo": "...", "texto": "..."}]
}
{$secoes}
Regras: não copie dados de quem RECEBE o documento (cliente), nem itens, quantidades ou valores: isso muda a cada documento. Quando um texto citar esses dados, troque por um destes marcadores: {$marcadores}. Mantenha a ordem do documento. Se algo não existir, use null ou lista vazia. Não invente nada que não esteja no documento.
PROMPT;
    }
}
