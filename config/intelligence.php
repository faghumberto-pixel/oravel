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
];
