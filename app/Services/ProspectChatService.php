<?php

namespace App\Services;

use App\Models\Prospect;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servico de chat com IA sobre o dataset global de Prospects.
 * Permite perguntas em linguagem natural ("quais prospects EPP nao contatei?",
 * "status dos prospects aguardando retorno", etc) e devolve resposta estruturada
 * + dados brutos pra exportacao (CSV/PDF).
 */
class ProspectChatService
{
    private const MODEL = 'claude-sonnet-5';

    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public function __construct(private AnthropicApiClient $client) {}

    /**
     * Processa uma pergunta do usuario sobre o dataset de prospects.
     *
     * @param  array<int, array{role: string, content: string, sql?: string}>  $history  turnos anteriores da conversa (sem a pergunta atual)
     * @return array{answer: string, data: array<int, array>, sql: string, raw: array}
     */
    public function ask(string $question, array $history = []): array
    {
        // 1. Pega uma amostra representativa dos prospects (pra caber no contexto)
        $prospects = $this->fetchProspectsSample();

        // 2. Monta o contexto pro Claude
        $context = $this->buildContext($prospects);

        // 3. System prompt que ensina o modelo a responder EM JSON estruturado
        $systemPrompt = $this->systemPrompt();

        // 4. Chama a API com o historico da conversa (se houver)
        $total = Prospect::count();
        $userContent = "Estatísticas gerais (banco inteiro, {$total} prospects):\n"
            .json_encode($this->buildStats(), JSON_UNESCAPED_UNICODE)
            ."\n\nAmostra dos {$prospects->count()} prospects mais relevantes (formato de exemplo, NÃO é a lista completa):\n"
            .json_encode($context, JSON_UNESCAPED_UNICODE)
            ."\n\nPergunta do usuário:\n".$question;

        $result = $this->client->sendConversation(
            $systemPrompt,
            [...$this->historyMessages($history), ['role' => 'user', 'content' => $userContent]],
            6000,
        );
        $this->logTokens('ask', $result);

        if (! $result['ok']) {
            return [
                'answer' => 'Erro ao consultar a IA: ' . ($result['error'] ?? 'desconhecido'),
                'data' => [],
                'sql' => '',
                'raw' => ['error' => $result['error']],
            ];
        }

        $parsed = $this->client->parseJson($result['text']);

        if ($parsed === null) {
            // Resposta cortada/invalida: tenta ao menos recuperar o campo "answer"
            // em vez de mostrar o JSON cru pro usuario.
            $answer = $result['text'];
            if (preg_match('/"answer"\s*:\s*"((?:[^"\\\\]|\\\\.)*)/s', $answer, $m)) {
                $answer = stripcslashes($m[1]);
            }

            return [
                'answer' => $answer,
                'data' => [],
                'sql' => '',
                'raw' => ['raw_text' => $result['text']],
            ];
        }

        // 5. Se a IA devolveu SQL, executa pra buscar dados reais completos.
        // Se a consulta falhar (coluna inexistente etc.), devolve o erro pra IA
        // corrigir UMA vez; se mesmo assim nao der, avisa o usuario em vez de
        // ficar mudo (antes a lista vinha vazia e os botoes de download sumiam).
        $data = [];
        $sql = $parsed['sql'] ?? '';
        $aviso = '';

        if (filled($sql)) {
            [$data, $erro] = $this->runSql($sql);

            if ($erro !== null) {
                $retry = $this->client->sendConversation($systemPrompt, [
                    ...$this->historyMessages($history),
                    ['role' => 'user', 'content' => $userContent],
                    ['role' => 'assistant', 'content' => $result['text']],
                    ['role' => 'user', 'content' => "A consulta falhou com o erro: {$erro}\nCorrija usando SOMENTE colunas do schema e responda de novo no mesmo formato JSON."],
                ], 6000);
                $this->logTokens('retry', $retry);

                $parsed2 = $retry['ok'] ? $this->client->parseJson($retry['text']) : null;

                if ($parsed2 !== null && filled($parsed2['sql'] ?? '')) {
                    $parsed = $parsed2;
                    $sql = $parsed2['sql'];
                    [$data, $erro] = $this->runSql($sql);
                }
            }

            if ($erro !== null) {
                $aviso = "\n\nNão consegui montar a lista para essa pergunta. Tente reformular o pedido (ex.: citando o status, a cidade ou a nota).";
                $data = [];
            } elseif ($data === []) {
                $aviso = "\n\nNenhum prospect encontrado com esse critério.";
            }
        }

        return [
            'answer' => ($parsed['answer'] ?? 'Análise concluída.').$aviso,
            'data' => $data,
            'sql' => $sql,
            'raw' => $parsed,
        ];
    }

    /**
     * Converte o historico da conversa no formato da API (alterna user/assistant,
     * comeca em 'user', ultimas 4 mensagens) pra IA lembrar do que ja foi dito
     * e responder como um agente em conversa, nao a cada pergunta isolada.
     *
     * @param  array<int, array{role: string, content: string, sql?: string}>  $history
     * @return array<int, array{role: string, content: string}>
     */
    private function historyMessages(array $history): array
    {
        $out = [];

        foreach (array_slice($history, -4) as $m) {
            $role = ($m['role'] ?? '') === 'user' ? 'user' : 'assistant';
            $text = trim((string) ($m['content'] ?? ''));

            if ($text === '' || ($out === [] && $role !== 'user')) {
                continue;
            }

            if ($out !== [] && $out[array_key_last($out)]['role'] === $role) {
                continue;
            }

            if ($role === 'assistant' && filled($m['sql'] ?? '')) {
                $text .= "\n[SQL usado: {$m['sql']}]";
            }

            $out[] = ['role' => $role, 'content' => $text];
        }

        if ($out !== [] && $out[array_key_last($out)]['role'] === 'user') {
            array_pop($out);
        }

        return $out;
    }

    /**
     * Busca uma amostra representativa (max 8; o resto vem das estatisticas e do SQL) pra caber no contexto da API.
     * Prioriza: prospects com interacoes recentes, nota alta, retorno vencido.
     */
    private function fetchProspectsSample(int $limit = 8)
    {
        return Prospect::query()
            ->select([
                'id', 'nome', 'razao_social', 'cnpj', 'cidade', 'atividade', 'porte',
                'capital', 'ano_abertura', 'nota', 'regiao_campinas', 'empresa_nova',
                'status', 'resultado', 'lote', 'origem', 'socio', 'socio_cargo',
                'telefone', 'email', 'proximo_contato', 'ultimo_contato_em',
                'observacoes', 'o_que_olhar', 'mensagem_personalizada'
            ])
            ->orderByRaw("
                CASE
                    WHEN status IN ('em_teste', 'reuniao_agendada', 'proposta_enviada') THEN 1
                    WHEN proximo_contato IS NOT NULL AND proximo_contato <= NOW() THEN 2
                    WHEN nota >= 8 THEN 3
                    WHEN ultimo_contato_em IS NOT NULL THEN 4
                    ELSE 5
                END,
                nota DESC,
                ultimo_contato_em DESC NULLS LAST
            ")
            ->limit($limit)
            ->get();
    }

    /**
     * Converte a collection de prospects em array pro JSON do contexto.
     *
     * @return array<int, array>
     */
    private function buildContext($prospects): array
    {
        return $prospects->map(function ($p) {
            $ultimoContato = $p->ultimo_contato_em;
            $proximoContato = $p->proximo_contato;
            $diasSemContato = $ultimoContato ? (int) now()->diffInDays($ultimoContato) : null;
            $diasProximoContato = $proximoContato ? (int) now()->diffInDays($proximoContato, false) : null;
            $followUpVencido = $proximoContato && $proximoContato->lte(now());
            $ehCelular = (bool) preg_match('/^\(\d\d\) 9/', (string) $p->telefone);

            return [
                'id' => $p->id,
                'nome' => $p->nome,
                'razao_social' => $p->razao_social,
                'cnpj' => $p->cnpj,
                'cidade' => $p->cidade,
                'atividade' => $p->atividade,
                'porte' => $p->porte,
                'capital' => $p->capital,
                'ano_abertura' => $p->ano_abertura,
                'nota' => $p->nota,
                'regiao_campinas' => $p->regiao_campinas,
                'empresa_nova' => $p->empresa_nova,
                'status' => $p->status,
                'status_label' => Prospect::STATUS[$p->status] ?? $p->status,
                'resultado' => $p->resultado,
                'resultado_label' => $p->resultado ? (Prospect::RESULTADOS[$p->resultado] ?? $p->resultado) : null,
                'lote' => $p->lote,
                'origem' => $p->origem,
                'socio' => $p->socio,
                'socio_cargo' => $p->socio_cargo,
                'telefone' => $p->telefone,
                'eh_celular' => $ehCelular,
                'email' => $p->email,
                'ultimo_contato_em' => $ultimoContato?->format('Y-m-d H:i:s'),
                'dias_sem_contato' => $diasSemContato,
                'proximo_contato' => $proximoContato?->format('Y-m-d'),
                'dias_proximo_contato' => $diasProximoContato,
                'follow_up_vencido' => $followUpVencido,
                'observacoes' => $p->observacoes ? mb_substr((string) $p->observacoes, 0, 120) : null,
                'o_que_olhar' => $p->o_que_olhar ? mb_substr((string) $p->o_que_olhar, 0, 120) : null,
                'mensagem_personalizada' => $p->mensagem_personalizada ? mb_substr((string) $p->mensagem_personalizada, 0, 120) : null,
            ];
        })->values()->all();
    }

    /**
     * Resumo agregado do banco inteiro (contagens), barato em tokens, pra IA
     * responder perguntas de panorama sem precisar de uma amostra grande.
     *
     * @return array<string, mixed>
     */
    private function buildStats(): array
    {
        $contagem = fn (string $col, int $top = 12) => Prospect::query()
            ->whereNotNull($col)
            ->selectRaw("{$col} as k, COUNT(*) as n")
            ->groupBy($col)
            ->orderByDesc('n')
            ->limit($top)
            ->pluck('n', 'k')
            ->all();

        return [
            'por_status' => $contagem('status'),
            'por_resultado' => $contagem('resultado'),
            'por_porte' => $contagem('porte'),
            'por_cidade_top12' => $contagem('cidade'),
            'por_lote' => $contagem('lote', 30),
            'retorno_vencido' => Prospect::whereNotNull('proximo_contato')->where('proximo_contato', '<=', now())->count(),
            'sem_nenhum_contato' => Prospect::whereNull('ultimo_contato_em')->count(),
            'regiao_campinas' => Prospect::where('regiao_campinas', true)->count(),
            'empresas_novas' => Prospect::where('empresa_nova', true)->count(),
            'nota_8_ou_mais' => Prospect::where('nota', '>=', 8)->count(),
        ];
    }

    /**
     * Registra no log o consumo REAL de tokens de cada chamada a API
     * (canal padrao; buscar por "prospect_chat_tokens").
     */
    private function logTokens(string $etapa, array $result): void
    {
        $u = $result['usage'] ?? null;

        if (! is_array($u)) {
            return;
        }

        Log::info('prospect_chat_tokens', [
            'etapa' => $etapa,
            'input' => $u['input_tokens'] ?? null,
            'output' => $u['output_tokens'] ?? null,
            'cache_read' => $u['cache_read_input_tokens'] ?? null,
            'cache_write' => $u['cache_creation_input_tokens'] ?? null,
        ]);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            Você é um analista de vendas B2B especializado em prospecção ativa para
            locação e manutenção de equipamentos pesados. O usuário (super admin da
            Oravel) faz perguntas em linguagem natural sobre o dataset COMPLETO de
            prospects (empresas que AINDA NÃO são clientes).

            Você recebe ESTATÍSTICAS do banco inteiro (contagens) e uma AMOSTRA pequena
            (até 8 registros, só de exemplo do formato). Para listas e números exatos use
            SEMPRE o SQL, nunca a amostra. Com base nessa amostra e no conhecimento
            do schema, você deve:

            1. Entender a pergunta do usuário
            2. Escrever um SQL PostgreSQL SEGURO (apenas SELECT, sem JOINs perigosos,
               sem subqueries complexas, apenas na tabela `prospects`) que responde
               à pergunta no banco REAL (não na amostra)
            3. Responder em linguagem natural com insights acionáveis

            Você conversa como um agente: use o histórico da conversa para entender
            perguntas de seguimento ("e desses, quais são de Campinas?", "exporta isso").
            Responda APENAS com um objeto JSON válido, sem markdown, sem crases,
            sem texto antes ou depois, no formato exato:

            {
              "answer": "string (resposta em português, direta e acionável, com números quando possível)",
              "sql": "string (SQL SELECT seguro, ou vazio se a pergunta não precisar de query)",
              "insights": ["string", "string"],
              "suggested_actions": ["string", "string"]
            }

            REGRAS DE SEGURANÇA DO SQL:
            - APENAS SELECT na tabela `prospects`
            - NENHUM JOIN, UPDATE, DELETE, INSERT, DROP, CREATE, ALTER
            - NENHUM comentário SQL (-- ou /* */)
            - Use apenas colunas que existem no schema abaixo
            - Limite máximo 500 linhas (LIMIT 500)
            - Use ILIKE para buscas textuais case-insensitive
            - Para "EPP" / "ME" / "MEI" / "LTDA" / "EIRELI": busque em `porte` OU `atividade` com ILIKE
            - Para "não contatei": status = 'nao_enviado' E ultimo_contato_em IS NULL
            - Para "aguardando retorno": proximo_contato <= NOW() E proximo_contato IS NOT NULL
            - Para "sem interação": ultimo_contato_em IS NULL
            - status: nao_enviado, enviado, respondeu, em_teste, sem_interesse, convertido
            - resultado: nao_atendeu, nao_respondeu, numero_invalido, ja_tem_sistema,
              sem_interesse_momento, sem_interesse, pediu_email, quer_reuniao,
              reuniao_agendada, proposta_enviada, contrato_andamento, fechado

            ATENÇÃO: o contexto que você recebe tem campos CALCULADOS que NÃO são colunas
            do banco: eh_celular, dias_sem_contato, dias_proximo_contato, follow_up_vencido,
            status_label, resultado_label. Nunca use esses nomes no SQL.
            - celular: telefone ~ '^\(\d\d\) 9'
            - sem contato há N dias: ultimo_contato_em < NOW() - INTERVAL 'N days'
            - follow-up vencido: proximo_contato <= CURRENT_DATE

            SCHEMA DA TABELA prospects:
            - id (uuid)
            - nome (varchar)
            - razao_social (varchar)
            - cnpj (varchar)
            - cidade (varchar)
            - atividade (varchar)
            - porte (varchar) -- ex: 'EPP', 'ME', 'MEI', 'LTDA', 'EIRELI', 'SA'
            - capital (numeric)
            - ano_abertura (integer)
            - nota (integer 0-10)
            - regiao_campinas (boolean)
            - empresa_nova (boolean) -- aberta desde 2023
            - status (varchar)
            - resultado (varchar, nullable)
            - lote (integer, nullable)
            - origem (varchar, nullable)
            - socio (varchar)
            - socio_cargo (varchar)
            - telefone (varchar)
            - email (varchar)
            - proximo_contato (date, nullable)
            - ultimo_contato_em (timestamp, nullable)
            - observacoes (text)
            - o_que_olhar (text)
            - mensagem_personalizada (text)
            - created_at, updated_at

            EXEMPLOS DE PERGUNTAS E SQL ESPERADO:
            - "Quais prospects são EPP e não contatei ainda?"
              -> SELECT * FROM prospects WHERE (porte ILIKE '%EPP%' OR atividade ILIKE '%EPP%') AND status = 'nao_enviado' AND ultimo_contato_em IS NULL LIMIT 500
            - "Quais estão aguardando retorno?"
              -> SELECT * FROM prospects WHERE proximo_contato IS NOT NULL AND proximo_contato <= CURRENT_DATE LIMIT 500
            - "Qual o status dos prospects?"
              -> SELECT status, COUNT(*) as total FROM prospects GROUP BY status ORDER BY total DESC
            - "Prospects com nota >= 8 na região de Campinas"
              -> SELECT * FROM prospects WHERE nota >= 8 AND regiao_campinas = true LIMIT 500
            - "Quantos prospects por lote?"
              -> SELECT lote, COUNT(*) as total FROM prospects WHERE lote IS NOT NULL GROUP BY lote ORDER BY lote

            LISTAS / EXPORTAÇÃO: quando o usuário pedir uma lista (de empresas, prospects,
            contatos etc.), NÃO escreva as linhas no "answer". Gere o SQL com as colunas
            úteis (nome, razao_social, cnpj, cidade, atividade, porte, socio, telefone,
            email, status, nota — nunca SELECT *) e responda em 1 ou 2 frases curtas dizendo
            o critério usado e que a lista está pronta para baixar em Excel ou CSV nos
            botões abaixo. Nunca fale em "SQL", "query" ou "consulta" para o usuário no answer.

            Em perguntas de seguimento ("só os de Campinas", "agora com telefone", "exporta
            isso"), SEMPRE gere o SQL COMPLETO e autossuficiente, com todos os filtros da
            conversa somados aos novos. Nunca deixe sql vazio quando o usuário quer uma
            lista ou arquivo: o arquivo só existe se o SQL rodar nesta resposta.

            Se a pergunta NÃO precisar de SQL (ex: "explique o que é EPP", "como faço follow-up"),
            retorne sql: "" vazio e responda direto no answer.
            PROMPT;
    }

    /**
     * Executa SQL de forma segura (apenas SELECT na tabela prospects).
     *
     * @return array{0: array<int, array>, 1: ?string} [linhas, mensagem de erro ou null]
     */
    private function runSql(string $sql): array
    {
        $sql = rtrim(trim($sql), "; \t\n\r");
        $sqlLower = strtolower($sql);

        if (! str_starts_with($sqlLower, 'select')) {
            return [[], 'apenas SELECT e permitido'];
        }

        // Palavras inteiras (\b): "created_at"/"updated_at" nao devem bloquear.
        if (preg_match('/\b(join|union|insert|update|delete|drop|create|alter|truncate|grant|revoke)\b|--|\/\*|\*\/|;/', $sqlLower)) {
            Log::warning('SQL bloqueado por segurança', ['sql' => $sql]);

            return [[], 'consulta usa comando nao permitido'];
        }

        if (! str_contains($sqlLower, 'from prospects') && ! str_contains($sqlLower, 'from "prospects"')) {
            return [[], 'a consulta deve ser FROM prospects'];
        }

        try {
            $results = \Illuminate\Support\Facades\DB::select($sql);

            return [array_map(fn ($row) => (array) $row, $results), null];
        } catch (\Throwable $e) {
            Log::warning('Erro ao executar SQL do chat', ['sql' => $sql, 'error' => $e->getMessage()]);

            return [[], mb_substr($e->getMessage(), 0, 300)];
        }
    }

    /**
     * Exporta dados para CSV.
     */
    public function exportCsv(array $data, string $filename = 'prospects-chat-export'): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return \Illuminate\Support\Facades\Response::stream(function () use ($data) {
            $out = fopen('php://temp', 'r+');
            fwrite($out, "\xEF\xBB\xBF");
            if (! empty($data)) {
                fputcsv($out, array_keys($data[0]), ';');
                foreach ($data as $row) {
                    fputcsv($out, array_values($row), ';');
                }
            }
            rewind($out);
            echo stream_get_contents($out);
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}-" . now()->format('Ymd-His') . ".csv\"",
        ]);
    }

    /**
     * Exporta dados para Excel.
     */
    public function exportExcel(array $data, string $filename = 'prospects-chat-export'): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return \Illuminate\Support\Facades\Response::stream(function () use ($data) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            if (! empty($data)) {
                $headers = array_keys($data[0]);
                $sheet->fromArray([$headers], null, 'A1');
                $sheet->getStyle('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1')->getFont()->setBold(true);

                $row = 2;
                foreach ($data as $record) {
                    $col = 'A';
                    foreach ($record as $value) {
                        $sheet->setCellValue($col . $row, $value);
                        $col++;
                    }
                    $row++;
                }

                foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers))) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            }

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}-" . now()->format('Ymd-His') . ".xlsx\"",
        ]);
    }
}