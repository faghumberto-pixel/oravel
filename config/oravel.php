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

];
