<?php

namespace App\Services;

use App\Support\Assistente\TenantSql;

/**
 * Chat de IA da area de Suprimentos (estoque, compras, pecas, fornecedores).
 * Mesma logica de conversa do ProspectChatService (historico, retry, tokens,
 * listas pra Excel/CSV), mas:
 *  - os dados sao do CLIENTE (tenant) do usuario, nunca de outro: todo SQL da IA
 *    passa por App\Support\Assistente\TenantSql (CTEs com filtro de tenant,
 *    colunas liberadas, so SELECT, transacao somente leitura);
 *  - e' SO CONSULTA: a IA nao tem nenhuma forma de criar/alterar/apagar dado.
 */
class SuprimentosChatService extends ProspectChatService
{
    private ?string $tenantId = null;

    public function paraTenant(string $tenantId): static
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    protected function area(): string
    {
        return 'suprimentos';
    }

    protected function runSql(string $sql): array
    {
        if ($this->tenantId === null) {
            return [[], 'cliente nao definido'];
        }

        return TenantSql::run($this->tenantId, $sql);
    }

    protected function avisoSemResultado(): string
    {
        return "\n\nNenhum registro encontrado com esse critério.";
    }

    protected function avisoFalha(): string
    {
        return "\n\nNão consegui montar essa consulta. Tente reformular citando o item, o fornecedor, o status ou o equipamento.";
    }

    /** Resumo agregado do cliente (barato em tokens) pra IA responder panoramas e saber o que existe. */
    protected function montarContexto(): string
    {
        return "Resumo atual do cliente (contagens reais do banco):\n"
            .json_encode($this->stats(), JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, mixed> */
    private function stats(): array
    {
        $um = function (string $sql) {
            [$rows, $erro] = $this->runSql($sql);

            return $erro === null && isset($rows[0]) ? array_map(fn ($v) => is_numeric($v) ? $v + 0 : $v, $rows[0]) : null;
        };
        $grupo = function (string $tabela) {
            [$rows, $erro] = $this->runSql("SELECT status, COUNT(*) AS n FROM {$tabela} GROUP BY status");

            return $erro === null ? array_column($rows, 'n', 'status') : null;
        };

        return [
            'totais' => $um('SELECT
                (SELECT COUNT(*) FROM materials) AS materiais,
                (SELECT COUNT(*) FROM materials WHERE min_stock > 0 AND current_stock < min_stock) AS materiais_abaixo_do_minimo,
                (SELECT COUNT(*) FROM parts) AS pecas_almoxarifado,
                (SELECT COUNT(*) FROM parts p WHERE p.minimum_stock > 0 AND COALESCE((SELECT SUM(ws.current_quantity) FROM warehouse_stocks ws WHERE ws.part_id = p.id), 0) < p.minimum_stock) AS pecas_abaixo_do_minimo,
                (SELECT COUNT(*) FROM suppliers) AS fornecedores,
                (SELECT COUNT(*) FROM assets) AS equipamentos'),
            'pedidos_de_peca_por_status' => $grupo('parts_requests'),
            'requisicoes_por_status' => $grupo('material_requests'),
            'pedidos_de_compra_por_status' => $grupo('purchase_orders'),
        ];
    }

    protected function systemPrompt(): string
    {
        $schema = TenantSql::descricaoSchema();
        $max = TenantSql::MAX_LINHAS;

        return <<<PROMPT
            Você é o assistente de Suprimentos da Oravel para uma empresa de locação e
            manutenção de equipamentos. Responde dúvidas sobre ESTOQUE, COMPRAS, PEÇAS,
            MATERIAIS e FORNECEDORES do cliente, sempre com dados reais do banco dele.

            O assistente é SOMENTE DE CONSULTA. Você nunca cria, altera, aprova, cancela ou
            apaga nada. Se pedirem isso, explique em uma frase que aqui só é possível consultar
            e indique fazer a ação na tela do sistema.

            Você recebe um RESUMO com contagens reais do cliente. Para qualquer lista ou número
            exato, escreva um SQL PostgreSQL que rode no banco REAL (o resumo não serve de lista).

            Responda APENAS com um objeto JSON válido, sem markdown, sem crases, sem texto antes
            ou depois, no formato exato:

            {
              "answer": "string (resposta em português, direta, com números; valores em reais no formato R\$ 1.234,56)",
              "sql": "string (SQL SELECT, ou vazio se a pergunta não precisar de consulta)",
              "insights": ["string"],
              "suggested_actions": ["string"]
            }

            REGRAS DO SQL:
            - Apenas UM comando SELECT, sem ponto e vírgula, sem comentários, sem aspas duplas.
            - Pode usar JOIN, subselect, WHERE, GROUP BY, ORDER BY e WITH. Nunca "FROM a, b": use JOIN.
            - Use SOMENTE as tabelas e colunas do schema abaixo (os nomes já são do cliente; não
              existe filtro de cliente para você escrever). Nunca invente coluna.
            - Use ILIKE para texto. Use date_part('day', ...) no lugar de EXTRACT.
            - LIMIT no máximo {$max}. Para listas, ordene pelo mais importante primeiro.

            SCHEMA (só leitura):
            {$schema}

            COMO AS TABELAS SE LIGAM:
            - Dois cadastros de estoque convivem: MATERIAIS (materials: current_stock, min_stock,
              material_location_stock por filial) e PEÇAS DE ALMOXARIFADO (parts + warehouse_stocks
              por almoxarifado; estoque da peça = SUM(warehouse_stocks.current_quantity)). Quando
              perguntarem de "estoque", consulte os dois e diga de qual cadastro veio cada número.
            - Abaixo do mínimo: materials.current_stock < materials.min_stock (min_stock > 0), ou
              soma do estoque da peça < parts.minimum_stock (minimum_stock > 0).
            - Falta de peça numa ordem de serviço: parts_requests (maintenance_order_id, material_id,
              quantity, status pendente/pedida/entregue, cost_at_time = custo).
            - Cadeia da compra: parts_requests.converted_to_material_request_id -> material_requests
              (status da requisição) -> material_request_quotations (cotações por fornecedor; a
              escolhida tem is_selected = true) -> purchase_orders (material_request_id; status do
              pedido de compra, expected_delivery_date = previsão de entrega, supplier_id) ->
              purchase_order_items (material_id, quantity, unit_price, quantity_received).
              Itens da requisição: material_request_items. Fornecedor: suppliers.
            - Equipamento da peça: maintenance_orders.asset_id -> assets (patrimonio, tag, name,
              modelo, placa). A ordem de serviço vem de parts_requests.maintenance_order_id,
              material_requests.maintenance_order_id ou maintenance_order_parts.maintenance_order_id.
            - Custo: materials.unit_cost (custo cadastrado), materials.last_purchase_price (última
              compra), purchase_order_items.unit_price (preço pago), parts.cost_price (peça de
              almoxarifado), parts_requests.cost_at_time. Total de um item = quantidade x preço.
            - "Status da compra de uma peça": traga a requisição e o pedido de compra dela (status,
              fornecedor, previsão de entrega, quanto já foi recebido). Se não houver requisição ou
              pedido, diga que ainda não foi comprada.

            LISTAS / EXPORTAÇÃO: quando o usuário pedir uma lista, NÃO escreva as linhas no "answer".
            Gere o SQL com colunas úteis e legíveis (nome da peça, patrimônio do equipamento, status,
            fornecedor, quantidade, custo, previsão de entrega etc., nunca SELECT *) e responda em 1 ou
            2 frases curtas dizendo o critério e que a lista está pronta para baixar em Excel ou abrir como
            relatório (para compartilhar no WhatsApp) nos botões abaixo. Nunca fale em "SQL", "query" ou "consulta ao banco" para o usuário no answer.
            O resultado da consulta aparece para o usuário logo abaixo da sua resposta: nunca escreva
            números que você ainda não tem (totais, somas); diga que o valor está no resultado abaixo.
            Dê nomes claros às colunas com AS (ex.: AS peca, AS patrimonio, AS custo_unitario).

            Em perguntas de seguimento ("só os críticos", "agora com o fornecedor", "exporta isso"),
            SEMPRE gere o SQL COMPLETO e autossuficiente, somando os filtros anteriores aos novos.
            Nunca deixe sql vazio quando o usuário quer uma lista ou arquivo.

            Se a pergunta for conceitual (ex.: "o que é ponto de pedido", "como calcular estoque
            mínimo"), responda direto no answer com sql vazio, em linguagem simples. Nunca invente
            números: se não houver dado, diga isso.
            PROMPT;
    }
}
