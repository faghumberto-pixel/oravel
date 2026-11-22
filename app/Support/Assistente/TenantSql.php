<?php

namespace App\Support\Assistente;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Executa o SQL que a IA do Assistente (area Suprimentos) escreve, SEM deixar
 * ela enxergar dado de outro cliente (tenant).
 *
 * Por que existe: o SQL da IA roda com DB::select (cru), e o global scope do
 * Eloquent (BelongsToTenant) NAO vale para SQL cru. Entao o isolamento e'
 * imposto aqui, em 4 camadas:
 *
 *  1. CTEs que "sombreiam" as tabelas: antes da consulta da IA, cada tabela
 *     liberada e' redefinida como `WITH parts AS (SELECT <colunas liberadas>
 *     FROM parts WHERE tenant_id = X ...)`. No Postgres, o nome da CTE vale
 *     para as referencias seguintes, e dentro do corpo da propria CTE o nome
 *     ainda e' a tabela real. A IA so enxerga linhas do cliente e so as
 *     colunas liberadas (sem dado bancario, certidoes etc.).
 *  2. Validacao do texto: so SELECT, so tabelas da lista, sem schema
 *     qualificado, sem funcoes de sistema, sem comentarios/;/aspas duplas.
 *  3. Transacao SOMENTE LEITURA com statement_timeout curto.
 *  4. Limite de 500 linhas por fora (subselect).
 *
 * Tabelas filhas sem tenant_id (warehouse_stocks, material_request_items) sao
 * filtradas pelo pai e por isso ficam ANTES dos pais na lista de CTEs (uma CTE
 * so enxerga as anteriores; assim o pai ainda e' a tabela real la dentro).
 */
class TenantSql
{
    public const MAX_LINHAS = 500;

    public const TIMEOUT = '5s';

    /**
     * Tabelas liberadas. 'tenant' => 'direct' (coluna tenant_id), 'global' (agregado anonimo, sem filtro) ou
     * ['pai' => tabela, 'fk' => coluna na filha] (filtra pelo tenant do pai).
     * 'soft' => true quando a tabela tem deleted_at (linhas apagadas ficam de fora,
     * igual ao Eloquent). 'desc' vai pro prompt da IA.
     *
     * @var array<string, array{cols: list<string>, tenant: string|array, soft?: bool, desc: string}>
     */
    public const TABELAS = [
        // --- filhas (antes dos pais) ---
        'warehouse_stocks' => [
            'cols' => ['id', 'warehouse_id', 'part_id', 'current_quantity', 'reserved_quantity'],
            'tenant' => ['pai' => 'warehouses', 'fk' => 'warehouse_id'],
            'desc' => 'saldo de cada peca (parts) em cada almoxarifado (warehouses). Estoque da peca = soma de current_quantity.',
        ],
        'material_request_items' => [
            'cols' => ['id', 'material_request_id', 'material_id', 'quantity', 'brand', 'cost_price'],
            'tenant' => ['pai' => 'material_requests', 'fk' => 'material_request_id'],
            'desc' => 'itens (material, quantidade, custo) de cada requisicao de material.',
        ],
        // --- pecas / almoxarifados (sistema Part) ---
        'parts' => [
            'cols' => ['id', 'sku', 'name', 'unit_of_measure', 'cost_price', 'minimum_stock', 'maximum_stock', 'location_shelf', 'is_active', 'part_category_id'],
            'tenant' => 'direct', 'soft' => true,
            'desc' => 'cadastro de pecas (sistema de almoxarifados). cost_price = custo unitario.',
        ],
        'part_categories' => [
            'cols' => ['id', 'name'],
            'tenant' => 'direct', 'soft' => true,
            'desc' => 'categorias das pecas.',
        ],
        'warehouses' => [
            'cols' => ['id', 'name', 'code', 'city', 'state', 'is_active', 'type', 'vehicle_plate'],
            'tenant' => 'direct', 'soft' => true,
            'desc' => 'almoxarifados (type: fixo ou mobile = veiculo/tecnico).',
        ],
        'stock_movements' => [
            'cols' => ['id', 'part_id', 'warehouse_id', 'movement_type', 'quantity', 'balance_after', 'unit_cost', 'total_cost', 'reference_document', 'created_at', 'work_order_id'],
            'tenant' => 'direct',
            'desc' => 'movimentacoes de estoque das pecas (entradas, saidas, consumo em OS). work_order_id = maintenance_orders.id.',
        ],
        'maintenance_order_parts' => [
            'cols' => ['id', 'maintenance_order_id', 'part_id', 'warehouse_id', 'quantity', 'unit_price'],
            'tenant' => 'direct',
            'desc' => 'pecas (parts) aplicadas em cada ordem de servico, com preco unitario.',
        ],
        // --- materiais / compras (sistema Material) ---
        'materials' => [
            'cols' => ['id', 'sku', 'name', 'unit_cost', 'min_stock', 'max_stock', 'current_stock', 'part_number', 'brand_name', 'unit_of_measure', 'supplier_id', 'last_purchase_price', 'warehouse_location', 'material_category_id'],
            'tenant' => 'direct',
            'desc' => 'cadastro de materiais/pecas (sistema de compras). current_stock = estoque atual; min_stock = estoque minimo; unit_cost = custo unitario.',
        ],
        'material_categories' => [
            'cols' => ['id', 'name'],
            'tenant' => 'direct', 'soft' => true,
            'desc' => 'categorias dos materiais.',
        ],
        'material_location_stock' => [
            'cols' => ['id', 'material_id', 'internal_unit_id', 'current_quantity', 'minimum_threshold', 'maximum_threshold'],
            'tenant' => 'direct',
            'desc' => 'saldo de cada material por filial/unidade interna (internal_units).',
        ],
        'internal_units' => [
            'cols' => ['id', 'name', 'code', 'city', 'state', 'is_active'],
            'tenant' => 'direct', 'soft' => true,
            'desc' => 'filiais / unidades internas.',
        ],
        'material_stock_movements' => [
            'cols' => ['id', 'material_id', 'type', 'quantity', 'balance_after', 'reference_type', 'reference_id', 'reason', 'document_reference', 'created_at'],
            'tenant' => 'direct',
            'desc' => 'movimentacoes de estoque dos materiais (type: entrada, saida, ajuste).',
        ],
        'suppliers' => [
            'cols' => ['id', 'name', 'document', 'email', 'phone', 'inscricao_estadual'],
            'tenant' => 'direct',
            'desc' => 'fornecedores (document = CNPJ/CPF).',
        ],
        'parts_requests' => [
            'cols' => ['id', 'maintenance_order_id', 'material_id', 'quantity', 'status', 'cost_at_time', 'converted_to_material_request_id', 'created_at'],
            'tenant' => 'direct',
            'desc' => 'pedido de peca feito pela ordem de servico (material em falta). status: pendente, pedida, entregue. cost_at_time = custo na epoca.',
        ],
        'material_requests' => [
            'cols' => ['id', 'maintenance_order_id', 'status', 'requested_at', 'delivered_at', 'approved_at', 'priority', 'target_delivery_date', 'rejection_reason', 'origin', 'provider_name'],
            'tenant' => 'direct',
            'desc' => 'requisicoes de material (pedem a compra). status: rascunho, aguardando_aprovacao, aprovado, recusado, cotado, a_caminho, entregue. priority: normal, urgente, critico. origin: manual, ordem_servico, reposicao_estoque, conversao_parts_request.',
        ],
        'material_request_quotations' => [
            'cols' => ['id', 'material_request_id', 'supplier_id', 'total_value', 'delivery_days', 'payment_terms', 'is_selected'],
            'tenant' => 'direct',
            'desc' => 'cotacoes de fornecedores para uma requisicao (is_selected = cotacao escolhida).',
        ],
        'purchase_orders' => [
            'cols' => ['id', 'material_request_id', 'material_request_quotation_id', 'supplier_id', 'status', 'total_value', 'expected_delivery_date', 'created_at'],
            'tenant' => 'direct',
            'desc' => 'pedidos de compra ao fornecedor. status: aberta, parcialmente_recebida, recebida, cancelada.',
        ],
        'purchase_order_items' => [
            'cols' => ['id', 'purchase_order_id', 'material_id', 'quantity', 'unit_price', 'quantity_received'],
            'tenant' => 'direct',
            'desc' => 'itens de cada pedido de compra (quantity_received = quanto ja chegou).',
        ],
        // --- ordens de servico / equipamentos ---
        'maintenance_orders' => [
            'cols' => ['id', 'os_number', 'asset_id', 'status', 'priority', 'description', 'created_at', 'scheduled_at', 'finished_at', 'material_cost', 'total_order_cost', 'maintenance_type', 'service_type'],
            'tenant' => 'direct', 'soft' => true,
            'desc' => 'ordens de servico de manutencao. asset_id = equipamento (assets).',
        ],
        'assets' => [
            'cols' => ['id', 'name', 'tag', 'patrimonio', 'serial_number', 'status', 'criticality', 'asset_category', 'fabricante', 'modelo', 'placa', 'acquisition_value'],
            'tenant' => 'direct', 'soft' => true,
            'desc' => 'equipamentos / ativos. patrimonio = numero de patrimonio do equipamento.',
        ],
    ];

    /** Palavras/trechos proibidos (testados sem os textos entre aspas simples). */
    private const PROIBIDO = '/(--|\/\*|\*\/|"|`|\\\\|\$\$)|\b(insert|update|delete|drop|create|alter|truncate|grant|revoke|copy|call|do|execute|set|reset|show|listen|notify|vacuum|analyze|refresh|lock|into|recursive|public|user|session_user|set_config|query_to_xml|xpath|dblink|version)\b|\bpg_|\bcurrent_(user|database|schema|schemas|setting|role|catalog|query)\b|\binformation_schema\b|\blo_(import|export)|\binet_|\btxid|\bfor\s+(update|share|no\s+key|key)\b/i';

    /**
     * Valida e monta o SQL final (CTEs do cliente + consulta da IA, com limite).
     *
     * @return array{0: ?string, 1: ?string} [sql completo, erro]
     */
    public static function preparar(string $tenantId, string $sql, ?array $tabelas = null): array
    {
        $tabelas ??= self::TABELAS;

        if (! self::tenantValido($tenantId)) {
            return [null, 'cliente invalido'];
        }

        $sql = rtrim(trim($sql), "; \t\n\r");

        if ($sql === '') {
            return [null, 'consulta vazia'];
        }

        if (str_contains($sql, ';')) {
            return [null, 'apenas uma consulta por vez (sem ponto e virgula)'];
        }

        $limpo = preg_replace("/'(?:[^']|'')*'/", "''", $sql); // sem o conteudo dos textos entre aspas simples

        if (! preg_match('/^\s*(select|with)\b/i', $limpo)) {
            return [null, 'apenas SELECT e permitido'];
        }

        if (preg_match(self::PROIBIDO, $limpo, $m)) {
            return [null, 'consulta usa trecho nao permitido: '.trim($m[0])];
        }

        // Nomes de CTE definidos pela propria consulta (WITH x AS (...)).
        $ctesDaIa = [];
        if (preg_match('/^\s*with\b/i', $limpo)) {
            preg_match_all('/\b([a-z_][a-z0-9_]*)\s+as\s*\(/i', $limpo, $mm);
            $ctesDaIa = array_map('strtolower', $mm[1]);
        }

        // Constructos "X FROM y" que nao sao tabela (extract/substring/trim/overlay/position).
        $semFuncoes = preg_replace('/\b(extract|substring|trim|overlay|position)\s*\((?:[^()]|\([^()]*\))*\)/i', '()', $limpo);

        preg_match_all('/\b(?:from|join)\s+([a-z_][a-z0-9_]*)/i', $semFuncoes, $tabs);
        foreach ($tabs[1] as $tab) {
            $tab = strtolower($tab);
            if (! isset($tabelas[$tab]) && ! in_array($tab, $ctesDaIa, true)) {
                return [null, "tabela ou coluna nao permitida depois de FROM/JOIN: {$tab}. Use apenas as tabelas do schema (e date_part() no lugar de EXTRACT)"];
            }
        }

        // FROM a, b (juncao antiga): nao confere a segunda tabela, entao exige JOIN.
        $palavrasFim = 'on|where|join|left|right|inner|outer|cross|group|order|limit|having|union|using|full|natural|offset|window|except|intersect';
        if (preg_match('/\b(?:from|join)\s+[a-z_][a-z0-9_]*(?:\s+(?:as\s+)?(?!(?:'.$palavrasFim.')\b)[a-z_][a-z0-9_]*)?\s*,/i', $semFuncoes)) {
            return [null, 'use JOIN em vez de listar tabelas separadas por virgula no FROM'];
        }

        $ctes = self::ctes($tenantId, $tabelas);

        if (preg_match('/^\s*with\b/i', $sql)) {
            // Consulta da IA com WITH proprio: nao da pra embrulhar em subselect, entao
            // as CTEs do cliente vao na frente e o LIMIT e' conferido/acrescentado no fim.
            if (preg_match('/\blimit\s+(\d+)\s*$/i', $sql, $lm)) {
                if ((int) $lm[1] > self::MAX_LINHAS) {
                    return [null, 'LIMIT maximo e '.self::MAX_LINHAS];
                }
                $limite = '';
            } else {
                $limite = ' LIMIT '.self::MAX_LINHAS;
            }

            return ['WITH '.$ctes.', '.preg_replace('/^\s*with\s+/i', '', $sql).$limite, null];
        }

        return ['WITH '.$ctes.' SELECT * FROM ('.$sql.') AS resultado LIMIT '.self::MAX_LINHAS, null];
    }

    /**
     * @return array{0: array<int, array>, 1: ?string} [linhas, erro]
     */
    public static function run(string $tenantId, string $sql, ?array $tabelas = null): array
    {
        [$final, $erro] = self::preparar($tenantId, $sql, $tabelas);

        if ($final === null) {
            Log::warning('assistente_sql_bloqueado', ['erro' => $erro, 'sql' => $sql]);

            return [[], $erro];
        }

        try {
            $rows = [];

            try {
                DB::transaction(function () use ($final, &$rows) {
                    DB::statement('SET TRANSACTION READ ONLY');
                    DB::statement("SET LOCAL statement_timeout = '".self::TIMEOUT."'");

                    $rows = DB::select($final);

                    // So' leitura: desfaz de proposito. Dentro de uma transacao maior, o COMMIT/RELEASE deixaria
                    // "somente leitura" valendo pro resto dela e quebraria a proxima gravacao (ex.: consumo de IA).
                    throw new FimDaLeitura;
                });
            } catch (FimDaLeitura) {
                // esperado
            }

            return [array_map(fn ($r) => (array) $r, $rows), null];
        } catch (\Throwable $e) {
            Log::warning('assistente_sql_erro', ['sql' => $sql, 'erro' => $e->getMessage()]);

            return [[], mb_substr($e->getMessage(), 0, 300)];
        }
    }

    /** tenant_id e' UUID: so aceita esse formato (vai literal no SQL, entao nada alem disso passa). */
    public static function tenantValido(string $tenantId): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $tenantId);
    }

    /** `parts AS (...), suppliers AS (...)` com o filtro do cliente, na ordem de TABELAS. */
    public static function ctes(string $tenantId, ?array $tabelas = null): string
    {
        $tabelas ??= self::TABELAS;
        $tid = "'".strtolower($tenantId)."'"; // UUID ja validado por tenantValido()
        $partes = [];

        foreach ($tabelas as $tabela => $def) {
            $cols = implode(', ', $def['cols']);

            if ($def['tenant'] === 'global') {
                // Agregado anonimo entre empresas (sem tenant_id): so entra tabela que nunca guarda quem contribuiu.
                $where = 'TRUE';
            } elseif ($def['tenant'] === 'direct') {
                $where = "tenant_id = {$tid}".(! empty($def['soft']) ? ' AND deleted_at IS NULL' : '');
            } else {
                $pai = $def['tenant']['pai'];
                $fk = $def['tenant']['fk'];
                $paiSoft = ! empty($tabelas[$pai]['soft']) ? ' AND deleted_at IS NULL' : '';
                $where = "{$fk} IN (SELECT id FROM {$pai} WHERE tenant_id = {$tid}{$paiSoft})";
            }

            $partes[] = "{$tabela} AS (SELECT {$cols} FROM {$tabela} WHERE {$where})";
        }

        return implode(', ', $partes);
    }

    /** Texto do schema pro prompt da IA (uma unica fonte de verdade: TABELAS). */
    public static function descricaoSchema(?array $tabelas = null): string
    {
        $linhas = [];
        foreach ($tabelas ?? self::TABELAS as $tabela => $def) {
            $cols = implode(', ', $def['cols']);
            $linhas[] = "- {$tabela}({$cols}): {$def['desc']}";
        }

        return implode("\n", $linhas);
    }
}
