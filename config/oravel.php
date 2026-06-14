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

];
