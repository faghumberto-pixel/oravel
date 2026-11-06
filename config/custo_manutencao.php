<?php

/**
 * Custo de manutencao por equipamento (Inteligencia de Ativos): limiares da sinalizacao "revisar / trocar ou vender".
 * Razao = custo acumulado de manutencao / valor de aquisicao do equipamento.
 */
return [
    // A partir deste percentual do valor de aquisicao, considerar trocar ou vender.
    'limiar_trocar_vender' => (float) env('CUSTO_MANUTENCAO_LIMIAR_TROCAR', 0.60),

    // A partir deste percentual (e abaixo do anterior), revisar o equipamento.
    'limiar_revisar' => (float) env('CUSTO_MANUTENCAO_LIMIAR_REVISAR', 0.40),

    // Janela (em meses) da comparacao de tendencia: ultimos N meses x N meses anteriores.
    'janela_tendencia_meses' => 6,
];
