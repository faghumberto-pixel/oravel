<?php

namespace App\Domain\Intelligence\Suprimentos;

/**
 * Fonte única da planilha de Suprimentos: abas, colunas (o modelo .xlsx e o importador leem as mesmas) e indicadores.
 * Tipos de coluna: text, num (>= 0), int, date, enum, bool. 'futuro' = data futura permitida.
 */
class Catalogo
{
    public const ABA_DADOS = 'Dados';

    public const ABA_INVENTARIO = 'Inventário';

    public const ABA_COMPRAS = 'Compras';

    public const ABA_MOVIMENTOS = 'Movimentações';

    public const ABA_INSTRUCOES = 'Instruções';

    public const AREA = 'suprimentos';

    /** @return list<string> */
    public static function categorias(): array
    {
        return array_values((array) config('intelligence.suprimentos.categorias'));
    }

    /** @return list<array<string, mixed>> */
    public static function dados(): array
    {
        return [
            ['key' => 'data_referencia', 'title' => 'Data de Referência', 'type' => 'date', 'required' => true, 'help' => 'Dia em que o inventário foi conferido (dd/mm/aaaa).', 'exemplo' => '30/09/2026'],
            ['key' => 'periodo_dias', 'title' => 'Período de Análise (dias)', 'type' => 'int', 'required' => false, 'help' => 'Quantos dias para trás as compras e movimentações valem (30 a 365). Em branco = '.(int) config('intelligence.suprimentos.periodo_padrao_dias').'.', 'exemplo' => 90],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function inventario(): array
    {
        return [
            ['key' => 'codigo', 'title' => 'Código do Item', 'type' => 'text', 'required' => true, 'help' => 'Código único do item no seu estoque.', 'exemplo' => 'FIL-001'],
            ['key' => 'descricao', 'title' => 'Descrição', 'type' => 'text', 'required' => true, 'help' => 'Nome do item.', 'exemplo' => 'Filtro de óleo'],
            ['key' => 'categoria', 'title' => 'Categoria', 'type' => 'enum', 'required' => true, 'enum' => self::categorias(), 'help' => 'Uma destas: '.implode(', ', self::categorias()).'.', 'exemplo' => 'Peças'],
            ['key' => 'unidade', 'title' => 'Unidade', 'type' => 'text', 'required' => false, 'help' => 'un, kg, L, cx...', 'exemplo' => 'un'],
            ['key' => 'quantidade', 'title' => 'Quantidade em Estoque', 'type' => 'num', 'required' => true, 'help' => 'Quanto há hoje.', 'exemplo' => 24],
            ['key' => 'estoque_minimo', 'title' => 'Estoque Mínimo', 'type' => 'num', 'required' => false, 'help' => 'Abaixo disto o item pede reposição.', 'exemplo' => 10],
            ['key' => 'custo_unitario', 'title' => 'Custo Unitário (R$)', 'type' => 'num', 'required' => true, 'help' => 'Quanto custa uma unidade.', 'exemplo' => 48.9],
            ['key' => 'ultima_entrada', 'title' => 'Data da Última Entrada', 'type' => 'date', 'required' => false, 'help' => 'Quando chegou por último (dd/mm/aaaa).', 'exemplo' => '12/08/2026'],
            ['key' => 'ultima_saida', 'title' => 'Data da Última Saída', 'type' => 'date', 'required' => false, 'help' => 'Quando saiu por último (dd/mm/aaaa). Em branco = nunca saiu.', 'exemplo' => '21/09/2026'],
            ['key' => 'local', 'title' => 'Local de Armazenagem', 'type' => 'text', 'required' => false, 'help' => 'Almoxarifado, prateleira, depósito...', 'exemplo' => 'Almoxarifado 1'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function compras(): array
    {
        return [
            ['key' => 'pedido', 'title' => 'Nº do Pedido', 'type' => 'text', 'required' => false, 'help' => 'Número do pedido de compra.', 'exemplo' => 'PC-1042'],
            ['key' => 'data_pedido', 'title' => 'Data do Pedido', 'type' => 'date', 'required' => true, 'help' => 'Quando o pedido foi feito.', 'exemplo' => '02/09/2026'],
            ['key' => 'codigo_item', 'title' => 'Código do Item', 'type' => 'text', 'required' => true, 'help' => 'O mesmo código do Inventário.', 'exemplo' => 'FIL-001'],
            ['key' => 'fornecedor', 'title' => 'Fornecedor', 'type' => 'text', 'required' => true, 'help' => 'Quem vendeu.', 'exemplo' => 'Auto Peças Silva'],
            ['key' => 'quantidade', 'title' => 'Quantidade', 'type' => 'num', 'required' => true, 'help' => 'Quanto foi pedido.', 'exemplo' => 30],
            ['key' => 'valor_total', 'title' => 'Valor Total (R$)', 'type' => 'num', 'required' => true, 'help' => 'Valor do pedido.', 'exemplo' => 1467],
            ['key' => 'prazo_prometido', 'title' => 'Prazo Prometido', 'type' => 'date', 'required' => true, 'futuro' => true, 'help' => 'Data de entrega combinada com o fornecedor.', 'exemplo' => '12/09/2026'],
            ['key' => 'data_chegada', 'title' => 'Data da Chegada', 'type' => 'date', 'required' => false, 'help' => 'Quando chegou de fato. Em branco = ainda não chegou.', 'exemplo' => '15/09/2026'],
            ['key' => 'emergencial', 'title' => 'Compra Emergencial', 'type' => 'bool', 'required' => false, 'help' => 'Sim se foi comprada às pressas por falta do item. Em branco = Não.', 'exemplo' => 'Não'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function movimentos(): array
    {
        return [
            ['key' => 'data', 'title' => 'Data', 'type' => 'date', 'required' => true, 'help' => 'Dia da entrada ou da saída.', 'exemplo' => '18/09/2026'],
            ['key' => 'codigo_item', 'title' => 'Código do Item', 'type' => 'text', 'required' => true, 'help' => 'O mesmo código do Inventário.', 'exemplo' => 'FIL-001'],
            ['key' => 'tipo', 'title' => 'Tipo', 'type' => 'enum', 'required' => true, 'enum' => ['Entrada', 'Saída'], 'help' => 'Entrada ou Saída.', 'exemplo' => 'Saída'],
            ['key' => 'quantidade', 'title' => 'Quantidade', 'type' => 'num', 'required' => true, 'help' => 'Quanto entrou ou saiu.', 'exemplo' => 4],
            ['key' => 'destino', 'title' => 'Destino', 'type' => 'text', 'required' => false, 'help' => 'Para onde foi (obra, equipamento, veículo, oficina, setor...). Nas entradas, pode ficar em branco.', 'exemplo' => 'Oficina - Munck 12'],
            ['key' => 'documento', 'title' => 'Documento ou Referência', 'type' => 'text', 'required' => false, 'help' => 'Nº da requisição, OS ou nota.', 'exemplo' => 'OS-3381'],
        ];
    }

    /**
     * Indicadores de Suprimentos. 'ruim' vale nota 0 e 'bom' nota 100 (linear nos dois sentidos; ver DiagnosticoCalculator).
     * LIMIARES DE REFERÊNCIA, ainda não validados com especialista do setor.
     *
     * @return array<string, array{titulo: string, unidade: string, ruim: ?float, bom: ?float, explica: string}>
     */
    public static function indicadores(): array
    {
        return [
            'valor_estoque' => ['titulo' => 'Valor em Estoque', 'unidade' => 'R$', 'ruim' => null, 'bom' => null, 'explica' => 'Dinheiro parado nas prateleiras hoje (quantidade × custo).'],
            'itens_abaixo_minimo_pct' => ['titulo' => 'Itens Abaixo do Mínimo', 'unidade' => '%', 'ruim' => 25.0, 'bom' => 5.0, 'explica' => 'Quanto do estoque com mínimo definido já pede reposição. Alto = risco de faltar.'],
            'itens_parados_pct' => ['titulo' => 'Itens Parados', 'unidade' => '%', 'ruim' => 30.0, 'bom' => 10.0, 'explica' => 'Itens com estoque e sem nenhuma saída há muito tempo.'],
            'valor_parado_pct' => ['titulo' => 'Valor Parado', 'unidade' => '%', 'ruim' => 30.0, 'bom' => 10.0, 'explica' => 'Quanto do valor em estoque está em itens sem saída. Alto = dinheiro encalhado.'],
            'giro_estoque' => ['titulo' => 'Giro do Estoque', 'unidade' => 'x/ano', 'ruim' => 1.5, 'bom' => 4.0, 'explica' => 'Quantas vezes o estoque se renova por ano, pelas saídas do período.'],
            'cobertura_dias' => ['titulo' => 'Cobertura do Estoque', 'unidade' => 'dias', 'ruim' => 180.0, 'bom' => 45.0, 'explica' => 'Por quantos dias o estoque atual dura no ritmo de consumo do período.'],
            'entregas_no_prazo_pct' => ['titulo' => 'Entregas no Prazo', 'unidade' => '%', 'ruim' => 60.0, 'bom' => 90.0, 'explica' => 'Pedidos que chegaram até o prazo prometido pelo fornecedor.'],
            'atraso_medio_dias' => ['titulo' => 'Atraso Médio dos Fornecedores', 'unidade' => 'dias', 'ruim' => 7.0, 'bom' => 1.0, 'explica' => 'Quantos dias, em média, o pedido chega depois do prometido.'],
            'pedidos_abertos_atrasados_pct' => ['titulo' => 'Pedidos em Aberto Atrasados', 'unidade' => '%', 'ruim' => 50.0, 'bom' => 10.0, 'explica' => 'Dos pedidos que ainda não chegaram, quantos já passaram do prazo.'],
            'compras_emergenciais_pct' => ['titulo' => 'Compras Emergenciais', 'unidade' => '%', 'ruim' => 25.0, 'bom' => 5.0, 'explica' => 'Compras feitas às pressas, em geral mais caras. Alto = falta de planejamento.'],
            'concentracao_fornecedor_pct' => ['titulo' => 'Dependência do Maior Fornecedor', 'unidade' => '%', 'ruim' => 70.0, 'bom' => 30.0, 'explica' => 'Quanto das compras está num único fornecedor.'],
            'saidas_sem_destino_pct' => ['titulo' => 'Saídas Sem Destino', 'unidade' => '%', 'ruim' => 40.0, 'bom' => 5.0, 'explica' => 'Saídas sem registro de para onde foram. Alto = estoque sem rastro.'],
        ];
    }
}
