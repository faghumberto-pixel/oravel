<?php

/**
 * Oravel Intelligence (pacote 'intelligence' em config/pacotes.php). Importação → Snapshot → Indicadores → Diagnóstico → Benchmark.
 * Cliente, contrato, plano, usuário e permissão são os da Central: aqui só mora a lógica própria do produto.
 */
return [
    // Áreas do diagnóstico; 'geral' é o índice da empresa, calculado a partir das demais.
    'areas' => [
        'frota' => 'Saúde da Frota',
        'financeira' => 'Saúde Financeira',
        'comercial' => 'Saúde Comercial',
        'oficina' => 'Saúde da Oficina',
        'operacional' => 'Saúde Operacional',
        'geral' => 'Índice Geral',
    ],

    // Benchmark nunca expõe uma empresa: grupo com menos empresas que isto não é publicado (nem calculado).
    'benchmark_minimo_empresas' => 5,

    // Dimensões de comparação do benchmark (colunas de intelligence_benchmarks).
    'benchmark_dimensoes' => ['regiao', 'porte', 'segmento'],

    // Análise por equipamento (aba "Equipamentos" da planilha). ATENÇÃO: limiares de REFERÊNCIA, ainda não validados com especialista
    // do setor. Os de custo ÷ valor usam as MESMAS variáveis de ambiente do ERP (config/custo_manutencao.php): cliente com os dois
    // produtos vê a mesma sinalização nos dois.
    'equipamentos' => [
        'versao_regras' => 1,
        'max_linhas' => 5000,
        'limiar_trocar_vender' => (float) env('CUSTO_MANUTENCAO_LIMIAR_TROCAR', 0.60),
        'limiar_revisar' => (float) env('CUSTO_MANUTENCAO_LIMIAR_REVISAR', 0.40),
        'reincidencia_revisar_pct' => 30.0,
        'reincidencia_minimo_corretivas' => 2, // 1 reincidência em 1 corretiva seria "100%": ruído, não sinal
        'custo_receita_revisar_pct' => 30.0,
        'utilizacao_baixa_pct' => 40.0,
        'disponibilidade_minima_subutilizado_pct' => 80.0,
        'disponibilidade_risco_pct' => 75.0,
        // divergência (em %) entre o total digitado na aba "Dados" e a soma da aba "Equipamentos" que gera aviso
        'divergencia_aviso_pct' => 5.0,
    ],

    // Módulo Suprimentos (planilha própria). ATENÇÃO: limiares e categorias de REFERÊNCIA, ainda não validados com especialista do setor.
    'suprimentos' => [
        // Categorias aceitas na coluna "Categoria" do Inventário. Valor fora da lista entra como "Outros" (com aviso).
        'categorias' => ['Peças', 'Materiais', 'Itens de Escritório', 'Equipamentos', 'Ferramentas', 'EPI e Uniformes', 'Limpeza e Copa', 'Outros'],
        'categoria_padrao' => 'Outros',
        // Item sem nenhuma saída há mais que isto (dias) é "parado".
        'dias_parado' => 90,
        // Janela de análise padrão (dias) quando a planilha não informa o período.
        'periodo_padrao_dias' => 90,
        'max_linhas' => 20000,
        'tamanho_lista' => 15,
        // Sugestão de compra de um item abaixo do mínimo: repor até este múltiplo do estoque mínimo.
        'alvo_x_minimo' => 2,
    ],
];
