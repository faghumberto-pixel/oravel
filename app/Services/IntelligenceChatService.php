<?php

namespace App\Services;

use App\Domain\Intelligence\Services\EvolucaoService;
use App\Domain\Intelligence\Support\Catalogo;
use App\Support\Assistente\Resultado;
use App\Support\Assistente\TenantSql;

/**
 * Agente de IA do Oravel Intelligence. Mesma mecanica de conversa dos outros chats (ProspectChatService), mas:
 *  - le SO a camada de inteligencia da empresa (snapshots, indicadores, diagnosticos) e o benchmark anonimo:
 *    nunca os dados operacionais do ERP (TABELAS abaixo e' a lista inteira que a IA enxerga);
 *  - e' SO CONSULTA: nenhuma forma de criar/alterar/apagar dado;
 *  - isolamento por empresa imposto por TenantSql (CTEs com filtro de tenant, so SELECT, transacao somente leitura).
 */
class IntelligenceChatService extends ProspectChatService
{
    /** Tudo que o agente consegue ler. O benchmark e' 'global' porque so guarda agregados de grupos com amostra minima, sem tenant. */
    public const TABELAS = [
        'intelligence_snapshots' => [
            'cols' => ['id', 'data_referencia', 'dados', 'created_at'],
            'tenant' => 'direct',
            'desc' => 'fotografias da empresa (uma por importacao). data_referencia = data dos numeros. dados = JSON com os campos enviados (ex.: dados->>\'porte\', dados->>\'regiao\', dados->>\'segmento\').',
        ],
        'intelligence_indicators' => [
            'cols' => ['id', 'snapshot_id', 'chave', 'valor', 'unidade', 'meta', 'created_at'],
            'tenant' => 'direct',
            'desc' => 'indicadores calculados de cada snapshot (uma linha por indicador). chave = codigo do indicador (ver lista abaixo); valor numerico.',
        ],
        'intelligence_diagnostics' => [
            'cols' => ['id', 'snapshot_id', 'area', 'pontuacao', 'faixa', 'detalhes', 'created_at'],
            'tenant' => 'direct',
            'desc' => 'nota 0-100 por area e o indice geral (area = geral) de cada snapshot. faixa: Excelente, Bom, Atencao ou Critico.',
        ],
        'intelligence_benchmarks' => [
            'cols' => ['indicador', 'regiao', 'porte', 'segmento', 'periodo', 'amostra', 'media', 'p25', 'mediana', 'p75'],
            'tenant' => 'global',
            'desc' => 'comparativo ANONIMO entre empresas, por indicador e grupo (regiao/porte/segmento; "*" = todos). Nunca identifica empresas. indicador = mesma chave de intelligence_indicators.',
        ],
    ];

    private ?string $tenantId = null;

    public function paraTenant(string $tenantId): static
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    protected function area(): string
    {
        return 'intelligence';
    }

    protected function tenantParaConsumo(): ?string
    {
        return $this->tenantId;
    }

    protected function runSql(string $sql): array
    {
        if ($this->tenantId === null) {
            return [[], 'cliente nao definido'];
        }

        [$linhas, $erro] = TenantSql::run($this->tenantId, $sql, self::TABELAS);

        return [Resultado::semCodigosInternos($linhas), $erro];
    }

    protected function avisoSemResultado(): string
    {
        return "\n\nNenhum registro encontrado. Se a empresa ainda não importou a planilha, não há histórico para consultar.";
    }

    protected function avisoFalha(): string
    {
        return "\n\nNão consegui montar essa consulta. Tente reformular citando o indicador, a área ou o período.";
    }

    /** Situação atual da empresa já calculada (barato em tokens): a IA responde panoramas sem SQL. */
    protected function montarContexto(): string
    {
        if ($this->tenantId === null || ! TenantSql::tenantValido($this->tenantId)) {
            return 'Empresa não identificada.';
        }

        $r = app(EvolucaoService::class)->resumo($this->tenantId);
        if ($r === null) {
            return 'A empresa ainda não importou nenhuma fotografia (snapshot): não há indicadores nem diagnóstico.';
        }

        $compacto = [
            'ultimo_snapshot' => $r['atual'],
            'snapshot_anterior' => $r['anterior'],
            'total_de_snapshots' => $r['total_snapshots'],
            'areas' => array_map(fn ($a) => ['area' => $a['chave'], 'nota' => $a['nota'], 'faixa' => $a['faixa'], 'variacao' => $a['delta']], $r['areas']),
            'indicadores' => array_map(fn ($i) => ['chave' => $i['chave'], 'valor' => $i['valor'], 'anterior' => $i['anterior'], 'variacao' => $i['delta'], 'sentido' => $i['sentido']], $r['indicadores']),
        ];

        return "Situação atual da empresa (já calculada, do último snapshot):\n".json_encode($compacto, JSON_UNESCAPED_UNICODE);
    }

    private function catalogoParaPrompt(): string
    {
        $linhas = [];
        foreach (Catalogo::indicadores() as $chave => $d) {
            $meta = $d['ruim'] === null ? 'só informativo (sem nota)' : ($d['bom'] > $d['ruim'] ? 'quanto maior, melhor' : 'quanto menor, melhor');
            $linhas[] = "- {$chave}: {$d['titulo']} ({$d['unidade']}), área {$d['area']}, {$meta}";
        }

        return implode("\n", $linhas);
    }

    protected function systemPrompt(): string
    {
        $schema = TenantSql::descricaoSchema(self::TABELAS);
        $indicadores = $this->catalogoParaPrompt();
        $areas = collect(config('intelligence.areas'))->map(fn ($r, $k) => "{$k} = {$r}")->implode('; ');
        $max = TenantSql::MAX_LINHAS;
        $minimo = (int) config('intelligence.benchmark_minimo_empresas', 5);

        return <<<PROMPT
            Você é o assistente do Oravel Intelligence para uma empresa de locação e manutenção de
            equipamentos. Ajuda o gestor a entender como a empresa está e COMO EVOLUIU, usando só a camada
            de inteligência dela: fotografias (snapshots), indicadores, diagnósticos e o benchmark anônimo.
            Você NÃO tem acesso aos dados operacionais (ordens de serviço, contratos, clientes etc.).
            Se perguntarem algo que só esses dados responderiam, diga que o Intelligence trabalha com
            as fotografias importadas e sugira consultar a tela correspondente do sistema.

            O assistente é SOMENTE DE CONSULTA. Nunca cria, altera, importa ou apaga nada. Se pedirem,
            explique em uma frase que aqui só é possível consultar (a importação é feita na tela Importações).

            Você recebe um RESUMO já calculado do último snapshot. Para histórico, comparações entre datas,
            listas ou números exatos, escreva um SQL PostgreSQL que rode no banco REAL.

            Responda APENAS com um objeto JSON válido, sem markdown, sem crases, sem texto antes
            ou depois, no formato exato:

            {
              "answer": "string (resposta em português, direta, com números; valores em reais no formato R\$ 1.234,56)",
              "sql": "string (SQL SELECT, ou vazio se o resumo já responde ou a pergunta for conceitual)",
              "insights": ["string"],
              "suggested_actions": ["string"]
            }

            REGRAS DO SQL:
            - Apenas UM comando SELECT, sem ponto e vírgula, sem comentários, sem aspas duplas.
            - Pode usar JOIN, subselect, WHERE, GROUP BY, ORDER BY e WITH. Nunca "FROM a, b": use JOIN.
            - Use SOMENTE as tabelas e colunas do schema abaixo (já são da empresa; não existe filtro de
              cliente para você escrever). Nunca invente coluna.
            - Para campos dentro de "dados" use o operador ->> (ex.: dados->>'porte'). Use ILIKE para texto
              e date_part no lugar de EXTRACT. LIMIT no máximo {$max}.
            - Evolução: junte intelligence_indicators (ou diagnostics) com intelligence_snapshots pelo
              snapshot_id e ordene por data_referencia.

            SCHEMA (só leitura):
            {$schema}

            ÁREAS DO DIAGNÓSTICO: {$areas}.
            Nota de 0 a 100; faixas: Excelente (80+), Bom (60+), Atenção (35+), Crítico (abaixo de 35).
            As notas usam metas de referência iniciais, ainda não calibradas com o mercado: ao explicar uma
            nota, deixe claro que é uma referência e não um veredito absoluto.

            INDICADORES (chave = valor da coluna chave / indicador):
            {$indicadores}

            BENCHMARK: só existem linhas para grupos com pelo menos {$minimo} empresas. Para comparar, junte o
            indicador da empresa com intelligence_benchmarks pelo nome do indicador e pelo grupo dela (porte,
            regiao e segmento do snapshot; "*" vale para todos). Se não houver linha, diga que ainda não há
            empresas suficientes para comparar. NUNCA tente identificar nem estimar outra empresa.

            LISTAS / EXPORTAÇÃO: quando o usuário pedir uma lista ou série, NÃO escreva as linhas no "answer".
            Gere o SQL com colunas legíveis (nome do indicador, data, valor, unidade; nunca SELECT *) e responda
            em 1 ou 2 frases dizendo o critério e que a lista está pronta nos botões abaixo. Nunca fale em
            "SQL" ou "consulta ao banco" para o usuário. O resultado aparece abaixo da sua resposta: nunca
            escreva números que você ainda não tem (totais, médias); diga que o valor está no resultado abaixo.
            Os números que já estão no RESUMO você pode citar direto.
            NUNCA inclua códigos internos nos resultados: nada de id, uuid ou colunas terminadas em _id.
            Mostre o nome do indicador (não a chave), a data e o valor. Use ids só dentro do JOIN.
            Dê nomes claros às colunas com AS (ex.: AS indicador, AS data, AS valor).

            Em perguntas de seguimento, SEMPRE gere o SQL COMPLETO, somando os filtros anteriores aos novos.
            Se a pergunta for conceitual (ex.: "o que é disponibilidade da frota"), responda direto, sql vazio,
            em linguagem simples. Nunca invente números: se não houver dado, diga isso.
            PROMPT;
    }
}
