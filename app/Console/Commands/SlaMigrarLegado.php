<?php

namespace App\Console\Commands;

use App\Models\MaintenanceOrder;
use App\Models\SlaTimer;
use App\Services\Sla\SlaService;
use Illuminate\Console\Command;

/**
 * F2: as OS que ja tinham sla_target_minutes (campo digitado a mao) ganham o cronometro "Atendimento", fechado ou aberto
 * conforme o que ja aconteceu com a OS. Dry-run por padrao; `--apply` grava. Idempotente (nao duplica cronometro).
 */
class SlaMigrarLegado extends Command
{
    protected $signature = 'sla:migrar-legado {--apply : Grava os cronometros (sem isso, so conta)}';

    protected $description = 'Cria o cronometro Atendimento para as O.S. que ja tinham sla_target_minutes';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('apply');
        $total = 0;

        MaintenanceOrder::withoutGlobalScopes()->whereNotNull('sla_target_minutes')->where('sla_target_minutes', '>', 0)->orderBy('created_at')
            ->chunk(200, function ($ordens) use ($aplicar, &$total) {
                foreach ($ordens as $os) {
                    $existe = SlaTimer::withoutGlobalScopes()->where('subject_type', MaintenanceOrder::class)->where('subject_id', $os->id)->where('clock', SlaTimer::SERVICE)->exists();

                    if ($existe) {
                        continue;
                    }

                    $total++;

                    if ($aplicar) {
                        SlaService::sincronizarLegado($os);
                        SlaService::avancar($os);
                    }
                }
            });

        $this->info(($aplicar ? 'Criados' : 'A criar').": {$total} cronômetro(s) Atendimento a partir de sla_target_minutes.");

        return self::SUCCESS;
    }
}
