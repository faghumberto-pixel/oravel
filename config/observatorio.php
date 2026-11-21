<?php

/**
 * Integracao Central -> Observatorio (contrato v1, repositorio oravel-observatorio/contrato/v1).
 * A Central so' ENVIA dados e le a definicao das pesquisas. Nao calcula nenhuma estatistica.
 * Segredos somente por ambiente; vazio = integracao desligada.
 */
return [
    'url' => env('OBSERVATORIO_URL'),            // ex.: https://admin.observatorio.oravel.org/api/v1
    'token' => env('OBSERVATORIO_TOKEN'),
    'segredo' => env('OBSERVATORIO_SEGREDO'),    // assinatura HMAC das chamadas
    // Chave do pseudonimo da empresa (empresa_ref = HMAC(prospect_id)). Existe SO' na Central e nunca
    // pode mudar: trocar quebra a continuidade entre entrevistas da mesma empresa.
    'ref_key' => env('OBSERVATORIO_REF_KEY'),
    'timeout' => 10,
    'cache_pesquisas_segundos' => 300,
    'max_tentativas' => 8,
];
