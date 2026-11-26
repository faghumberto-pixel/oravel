<?php

/**
 * Teste grátis do Oravel Intelligence (oravel.com.br/intelligence) e contratação do plano básico.
 * Nada de preço ou prazo fica fixo no código: tudo vem daqui (e do .env).
 */
return [
    'pacote' => 'intelligence',

    // Prazo do teste e quando ele começa a contar: 'cadastro' (primeiro acesso, depois de confirmar o e-mail; o cliente sabe desde o
    // início quanto tempo tem para assinar) ou 'primeiro_envio' (1ª planilha com sucesso).
    'dias' => (int) env('INTELLIGENCE_TRIAL_DIAS', 30),
    'inicio' => env('INTELLIGENCE_TRIAL_INICIO', 'cadastro'),

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

    // Limites de cadastro por IP (tentativas por minuto).
    'rate_limit_por_minuto' => (int) env('INTELLIGENCE_RATE_LIMIT', 5),

    // Perguntas ao agente de IA por dia, por cliente, durante o teste grátis (os planos pagos têm a cota em 'planos').
    'ia_consultas_por_dia' => (int) env('INTELLIGENCE_TRIAL_IA_POR_DIA', 1),

    /*
     | Degraus de plano, em ordem. CUMULATIVOS: cada um inclui tudo do anterior ('modulos' lista só o que ele ACRESCENTA).
     | 'basico' lê nome/preço/ciclo de 'plano' acima (uma fonte só). Módulos ainda não construídos aparecem nos degraus mas
     | só devem ser vendidos quando existirem (ver 'modulos_prontos').
     */
    'planos' => [
        'basico' => ['ia_por_dia' => (int) env('INTELLIGENCE_PLANO_IA_POR_DIA', 10), 'modulos' => ['base'], 'relatorios' => 'Relatórios do painel e link para WhatsApp'],
        'standard' => ['nome' => env('INTELLIGENCE_STANDARD_NOME', 'Oravel Intelligence - Standard'), 'preco' => (float) env('INTELLIGENCE_STANDARD_PRECO', 99.00), 'ia_por_dia' => (int) env('INTELLIGENCE_STANDARD_IA_POR_DIA', 25), 'modulos' => ['suprimentos'], 'relatorios' => 'Mais relatórios'],
        'premium' => ['nome' => env('INTELLIGENCE_PREMIUM_NOME', 'Oravel Intelligence - Premium'), 'preco' => (float) env('INTELLIGENCE_PREMIUM_PRECO', 199.00), 'ia_por_dia' => (int) env('INTELLIGENCE_PREMIUM_IA_POR_DIA', 50), 'modulos' => ['comercial', 'logistica'], 'relatorios' => 'Todos os relatórios'],
    ],
    'modulos_rotulos' => ['base' => 'Frota, financeira, oficina e operacional', 'suprimentos' => 'Suprimentos', 'comercial' => 'Comercial', 'logistica' => 'Logística'],
    // Módulos que já existem de verdade. Só planos com todos os módulos prontos podem ser contratados.
    'modulos_prontos' => ['base'],

    // Dados de exemplo (demonstração) mostrados na 1ª abertura: somem de vez N dias depois, sem volta.
    'exemplo_dias' => (int) env('INTELLIGENCE_EXEMPLO_DIAS', 30),

    // Confirmação de e-mail do cadastro: validade do link e domínios de e-mail descartável que não são aceitos.
    'confirmacao_horas' => (int) env('INTELLIGENCE_CONFIRMACAO_HORAS', 24),
    'email_bloqueados' => ['mailinator.com', 'yopmail.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com', 'temp-mail.org', 'trashmail.com', 'sharklasers.com', 'getnada.com', 'dispostable.com', 'maildrop.cc', 'throwawaymail.com'],

    'termos_url' => env('INTELLIGENCE_TERMOS_URL', 'https://oravel.com.br/termos'),
    'privacidade_url' => env('INTELLIGENCE_PRIVACIDADE_URL', 'https://oravel.com.br/privacidade'),
];
