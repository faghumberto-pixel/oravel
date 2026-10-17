<?php

namespace App\Services\Cobranca;

/** Falha esperada da cobrança (config incompleta, cliente sem CPF/CNPJ, erro do Asaas): a mensagem pode ser mostrada ao usuário. */
class CobrancaException extends \RuntimeException {}
