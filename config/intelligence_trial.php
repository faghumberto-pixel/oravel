<?php

/**
 * Teste grátis do Oravel Intelligence (oravel.com.br/intelligence) e contratação do plano básico.
 * Nada de preço ou prazo fica fixo no código: tudo vem daqui (e do .env).
 */
return [
    'pacote' => 'intelligence',

    // Prazo do teste e quando ele começa a contar: 'primeiro_envio' (1ª planilha com sucesso) ou 'cadastro'.
    'dias' => (int) env('INTELLIGENCE_TRIAL_DIAS', 30),
    'inicio' => env('INTELLIGENCE_TRIAL_INICIO', 'primeiro_envio'),

    // Últimos N dias do teste = status "trial_expiring" (banner em destaque).
    'aviso_dias' => (int) env('INTELLIGENCE_TRIAL_AVISO_DIAS', 10),

    // Lembretes: dia do teste (contado desde o início) em que cada um sai. Registrados em intelligence_trial_lembretes (sem duplicar).
    'lembretes' => [20, 27, 30],

    // Dias de carência após a cobrança vencer antes de entrar em somente leitura.
    'carencia_dias' => (int) env('INTELLIGENCE_CARENCIA_DIAS', 5),

    'plano' => [
        'nome' => env('INTELLIGENCE_PLANO_NOME', 'Oravel Intelligence - Plano Básico'),
        'preco' => (float) env('INTELLIGENCE_PLANO_PRECO', 39.90),
        'ciclo' => 'MONTHLY',
    ],

    // Versão do texto do contrato (arquivo em resources/contratos/intelligence/{versao}.txt).
    'contrato_versao' => env('INTELLIGENCE_CONTRATO_VERSAO', 'v1'),

    // Quem recebe o aviso de cada novo cadastro (e-mail do dono).
    'notificar_email' => env('INTELLIGENCE_NOTIFICAR_EMAIL'),

    // Pedir novo link informando o WhatsApp: enquanto não houver canal de envio por WhatsApp (nem e-mail do cliente no
    // cadastro), o link é mostrado na tela. ATENÇÃO: quem souber o número consegue entrar na conta. Desligue assim que
    // houver envio por WhatsApp (então o link só é enviado, nunca mostrado).
    'mostrar_link_na_tela' => (bool) env('INTELLIGENCE_MOSTRAR_LINK_NA_TELA', true),

    // Limites de cadastro por IP (tentativas por minuto).
    'rate_limit_por_minuto' => (int) env('INTELLIGENCE_RATE_LIMIT', 5),

    'termos_url' => env('INTELLIGENCE_TERMOS_URL', 'https://oravel.com.br/termos'),
    'privacidade_url' => env('INTELLIGENCE_PRIVACIDADE_URL', 'https://oravel.com.br/privacidade'),
];
