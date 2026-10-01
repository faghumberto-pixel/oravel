<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super Admins da Plataforma
    |--------------------------------------------------------------------------
    | Lista explícita de e-mails com poder de super admin (acesso a TODOS os
    | tenants). Definida via .env, NUNCA editável pela interface da aplicação.
    | Ex.: SUPER_ADMINS="humberto@oravel.com.br,andrade@oravel.com.br"
    */

    'super_admins' => array_values(array_filter(array_map(
        fn ($email) => strtolower(trim($email)),
        explode(',', (string) env('SUPER_ADMINS', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Horímetro
    |--------------------------------------------------------------------------
    | Salto (em horas) entre duas leituras de horímetro acima do qual o
    | apontamento pede confirmação explícita antes de salvar (provável erro
    | de digitação). Ver App\Observers\HorimeterReadingObserver.
    */

    'horimeter_jump_threshold' => env('HORIMETER_JUMP_THRESHOLD', 500),

    /*
    |--------------------------------------------------------------------------
    | Cores da marca (painel admin)
    |--------------------------------------------------------------------------
    | Fonte única pras cores que hoje se repetiam em hex cru em vários lugares
    | (AdminPanelProvider.php via Color::hex(), theme.css e
    | brand-header-background.blade.php): mudar aqui reflete em todos.
    | Só o que de fato se repetia entra aqui -- não é um design system
    | completo, é o achado concreto de "caça ao hex em N arquivos" (análise
    | comparativa com outros SaaS, 2026-09-23). Lado PHP lê direto daqui
    | (config('oravel.brand.primary')); lado CSS lê via --oravel-primary
    | etc. (ver :root em brand-header-background.blade.php, que injeta
    | esses mesmos valores).
    */

    'brand' => [
        'primary' => '#ea580c',
        'sidebar_from' => '#0f172a',
        'sidebar_to' => '#1a2438',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dados da Oravel como CONTRATADA (Contrato de Assinatura)
    |--------------------------------------------------------------------------
    | Qualificação da Oravel nas cláusulas do contrato (razão social, CNPJ e
    | endereço da sede). Definidos via .env pra não ficarem escritos no código;
    | enquanto vazios, o contrato sai só com o nome "Oravel" e um aviso de
    | preenchimento pendente no preâmbulo -- NUNCA inventar esses dados.
    | Padrões = dados do rodapé do site institucional (oravel.com.br),
    | conferidos em 2026-10-01.
    | 'data_export_days' = prazo, após o término do contrato, em que o
    | Contratante ainda pode solicitar a exportação dos seus dados.
    */

    'company' => [
        'legal_name' => env('ORAVEL_LEGAL_NAME', 'Oravel Desenvolvimento de Software Ltda'),
        'cnpj' => env('ORAVEL_CNPJ', '65.707.953/0001-30'),
        'address' => env('ORAVEL_ADDRESS', 'Rua Pais Leme, 215, conj. 1713, Pinheiros, São Paulo/SP, CEP 05424-150'),
        'forum' => env('ORAVEL_FORUM', 'São Paulo/SP'),
        'data_export_days' => (int) env('ORAVEL_DATA_EXPORT_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Implantação (Anexo I do Contrato de Assinatura)
    |--------------------------------------------------------------------------
    | Prazo e limites do plano de implantação que o contrato promete ao cliente
    | (resources/views/partials/subscription-agreement-implementation.blade.php).
    | Valores padrão propostos em 2026-10-01 -- decisão comercial do dono do
    | produto, ajustáveis por .env sem mexer no texto do contrato.
    */

    'implementation' => [
        'days' => (int) env('ORAVEL_IMPLEMENTATION_DAYS', 30),
        'training_hours' => (int) env('ORAVEL_IMPLEMENTATION_TRAINING_HOURS', 8),
        'assisted_days' => (int) env('ORAVEL_IMPLEMENTATION_ASSISTED_DAYS', 15),
        'data_deadline_business_days' => (int) env('ORAVEL_IMPLEMENTATION_DATA_DEADLINE_DAYS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bloqueio por inadimplência
    |--------------------------------------------------------------------------
    | Dias corridos de atraso (Tenant.asaas_overdue_since) tolerados antes de
    | travar o acesso do tenant inteiro -- pedido do usuário 2026-09-23,
    | evita bloquear por atraso de 1 dia (cartão recusado, tentando de novo)
    | ou lentidão do próprio webhook. Cancelamento explícito
    | (PAYMENT_DELETED/PAYMENT_REFUNDED/CHECKOUT_CANCELED/CHECKOUT_EXPIRED
    | -> asaas_payment_status = 'cancelado') bloqueia IMEDIATAMENTE, sem
    | tolerância -- ver Tenant::isAccessBlockedForNonPayment().
    */

    'payment_grace_days' => env('ASAAS_PAYMENT_GRACE_DAYS', 5),

];
