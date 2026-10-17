<?php

/**
 * Complementos vendidos à parte, cobrados pela Oravel dentro da assinatura recorrente do cliente.
 * O valor NÃO fica aqui: é combinado por cliente e cadastrado na Central (Clientes → Complementos).
 */
return [
    'fidelidade_meses' => 12,

    // chave do módulo no contrato => nome para exibir
    'catalogo' => [
        'modulo_cobranca_automatica' => 'Cobrança automática',
        'modulo_fiscal_nfse' => 'Nota fiscal de serviço (NFS-e)',
    ],
];
