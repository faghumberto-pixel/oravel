<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'stripe' => [
        'public_key' => env('STRIPE_PUBLIC_KEY'),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'asaas' => [
        'api_key' => env('ASAAS_API_KEY'),
        // ASAAS_ENV=production|sandbox escolhe o endereço quando ASAAS_BASE_URL não está definida.
        'base_url' => env('ASAAS_BASE_URL', env('ASAAS_ENV') === 'production' ? 'https://api.asaas.com/v3' : 'https://sandbox.asaas.com/api/v3'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
    ],

    // Asaas DA EMPRESA CLIENTE (cada uma usa a própria conta e chave; o dinheiro não passa pela Oravel).
    'asaas_empresa' => [
        'urls' => [
            'sandbox' => env('ASAAS_EMPRESA_SANDBOX_URL', 'https://api-sandbox.asaas.com/v3'),
            'producao' => env('ASAAS_EMPRESA_PRODUCAO_URL', 'https://api.asaas.com/v3'),
        ],
    ],

    // Emissor fiscal (Focus NFe) na conta da Oravel: o token principal cadastra cada empresa cliente; cada empresa emite com o token próprio.
    'focusnfe' => [
        'driver' => env('FISCAL_DRIVER', 'focus'), // focus | fake
        'token' => env('FOCUSNFE_TOKEN'),
        'urls' => [
            'homologacao' => env('FOCUSNFE_HOMOLOGACAO_URL', 'https://homologacao.focusnfe.com.br'),
            'producao' => env('FOCUSNFE_PRODUCAO_URL', 'https://api.focusnfe.com.br'),
        ],
    ],

    // Consulta de CEP no cadastro (ViaCEP): confere se o CEP existe antes de pedir o pagamento. Desligada nos testes.
    'cep_lookup' => ['ativo' => env('CEP_LOOKUP', true)],

    'traccar' => [
        'url' => env('TRACCAR_URL'),
        'email' => env('TRACCAR_EMAIL'),
        'password' => env('TRACCAR_PASSWORD'),
    ],

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        // Modelo SO do chat da Central/Prospects (teste do Haiku, liberado pelo
        // usuario em 2026-11-15; o resto do sistema segue no Sonnet do
        // AnthropicApiClient). Pra voltar ao Sonnet: PROSPECT_CHAT_MODEL=claude-sonnet-5
        'prospect_chat_model' => env('PROSPECT_CHAT_MODEL', 'claude-haiku-5-5'),
        // Modelo de TODAS as analises de IA (send() do AnthropicApiClient: avaria, O.S., estoque, comercial, prospect...).
        // Haiku por decisao do usuario em 2026-11-16 (custo). Pra voltar ao Sonnet: ANTHROPIC_ANALYSIS_MODEL=claude-sonnet-5
        'analysis_model' => env('ANTHROPIC_ANALYSIS_MODEL', 'claude-haiku-5-5'),
    ],

    // Push do app do colaborador (Web Push): chaves geradas por `php artisan colaborador:gerar-chaves-push`.
    'webpush' => [
        'public' => env('VAPID_PUBLIC_KEY'),
        'private' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:contato@oravel.com.br'),
    ],

    'whatsapp' => [
        'token' => env('WHATSAPP_API_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com/v20.0'),
        'webhook_token' => env('WHATSAPP_WEBHOOK_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        // App da Oravel na Meta (Tech Provider): permite o "Conectar WhatsApp" (cadastro incorporado) dos clientes.
        'app_id' => env('WHATSAPP_APP_ID'),
        'embedded_config_id' => env('WHATSAPP_EMBEDDED_CONFIG_ID'),
    ],

    // Canais de entrada de leads (POST /api/inbound/leads): cada canal tem um token e pertence a UM
    // tenant, que é resolvido só pelo token (nunca por dado enviado no corpo). Hoje há um canal, o do
    // site da própria Oravel; outros tenants entram como novos canais. Token vazio = canal desligado.
    'inbound' => [
        'channels' => [
            'site-oravel' => [
                'token' => env('SITE_LEADS_SECRET'),
                'tenant_slug' => env('SITE_LEADS_TENANT_SLUG', 'oravel'),
                // 'central' = o lead entra no funil de VENDAS da Oravel (Central), invisivel para qualquer
                // tenant. Canais sem esta chave (outros tenants) criam CrmLead no CRM do proprio tenant.
                'destination' => 'central',
            ],
        ],
    ],

    // Canais do modulo Relacionamento Oravel (POST /api/relacionamento/eventos). Token vazio = canal desligado.
    'relacionamento' => [
        // Hosts do oravel.org autorizados a mandar sinais anonimos (ferramentas, downloads).
        // Segredo de assinatura dos webhooks do Sender (so' no .env, nunca no repositorio). Vazio = recusa tudo.
        'sender_secret' => env('RELACIONAMENTO_SENDER_SECRET'),
        'site_hosts' => ['oravel.org', 'www.oravel.org', 'oravel-org.web.app'],
        'channels' => [
            'oravel-org' => ['token' => env('RELACIONAMENTO_TOKEN_ORAVEL_ORG')],
        ],
    ],

];
