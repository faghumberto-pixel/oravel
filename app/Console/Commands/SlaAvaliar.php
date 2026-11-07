<?php

namespace App\Console\Commands;

use App\Services\Sla\SlaAlertas;
use Illuminate\Console\Command;

/** F3: avalia os cronometros de SLA abertos e envia os avisos de em risco, vencido e escalonado (cada um uma vez). */
class SlaAvaliar extends Command
{
    protected $signature = 'sla:avaliar';

    protected $description = 'Envia os avisos de SLA em risco, vencido e escalonado';

    public function handle(): int
    {
        $r = SlaAlertas::avaliar();
        $this->info("Avisos enviados: {$r['em_risco']} em risco, {$r['vencido']} vencido(s), {$r['escalonado']} escalonado(s).");

        return self::SUCCESS;
    }
}
