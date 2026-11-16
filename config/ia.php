<?php

return [
    // Cotacao usada no painel de consumo da Central (so estimativa; a cobranca da Anthropic e' em dolar).
    'dolar' => (float) env('IA_DOLAR', 5.56),

    // Consumo mais antigo que isso e' apagado (model:prune diario).
    'retencao_dias' => (int) env('IA_RETENCAO_DIAS', 400),
];
