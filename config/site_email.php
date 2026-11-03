<?php

/**
 * Site e e-mail institucional, oferecido no Contrato (Central):
 *  - incluso: gratuito no contrato de 12 meses; renova automaticamente com o contrato enquanto estiver pago;
 *  - avulso: vendido separado, cobrança ÚNICA no valor abaixo (valor padrão; cada contrato pode ajustar).
 * Mesmo que o cliente já tenha site e e-mail, a oferta continua valendo (só aparece como aviso interno na Central).
 */
return [
    'valor_avulso' => (float) env('ORAVEL_SITE_EMAIL_VALOR', 500),

    'modos' => [
        'nao' => 'Não incluído',
        'incluso' => 'Incluso, sem custo (contrato de 12 meses)',
        'avulso' => 'Vendido à parte',
    ],
];
