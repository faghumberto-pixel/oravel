<?php

/** Modulo Relacionamento Oravel (Central). Faixas do score e janela de tempo considerada. */
return [
    // So' interacoes dos ultimos N dias entram no score (engajamento esfria).
    'janela_dias' => 90,

    // Prazo de guarda do historico de uso do site (prometido na politica de privacidade do oravel.org).
    'retencao_meses' => 24,

    // Da maior para a menor faixa: o score cai na primeira cujo minimo ele alcanca.
    'faixas' => [
        ['min' => 80, 'rotulo' => 'Excelente', 'cor' => 'success'],
        ['min' => 60, 'rotulo' => 'Bom', 'cor' => 'info'],
        ['min' => 30, 'rotulo' => 'Médio', 'cor' => 'warning'],
        ['min' => 0, 'rotulo' => 'Baixo', 'cor' => 'gray'],
    ],
];
