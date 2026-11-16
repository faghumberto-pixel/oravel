<?php

namespace App\Support\Assistente;

/** Sinal interno: encerra (desfaz) a transacao somente leitura de TenantSql::run. */
final class FimDaLeitura extends \Exception {}
