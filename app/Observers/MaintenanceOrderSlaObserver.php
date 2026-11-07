<?php

namespace App\Observers;

use App\Models\MaintenanceOrder;
use App\Services\Sla\SlaService;

/**
 * F2: liga a O.S. aos cronometros de SLA. Nunca derruba a gravacao da OS por causa do SLA (erro vai pro log).
 * first_response_at = primeiro retorno: tecnico atribuido, agendamento ou inicio do servico -- o que vier primeiro.
 */
class MaintenanceOrderSlaObserver
{
    public function creating(MaintenanceOrder $os): void
    {
        if (blank($os->first_response_at) && (filled($os->technician_id) || filled($os->scheduled_at))) {
            $os->first_response_at = now();
        }
    }

    public function updating(MaintenanceOrder $os): void
    {
        if (blank($os->first_response_at)
            && (($os->isDirty('technician_id') && filled($os->technician_id))
                || ($os->isDirty('scheduled_at') && filled($os->scheduled_at))
                || ($os->isDirty('status') && $os->status === 'Em Andamento'))) {
            $os->first_response_at = now();
        }
    }

    public function created(MaintenanceOrder $os): void
    {
        $this->seguro(fn () => SlaService::iniciar($os));
    }

    public function updated(MaintenanceOrder $os): void
    {
        $this->seguro(function () use ($os) {
            if ($os->wasChanged('sla_target_minutes')) {
                SlaService::sincronizarLegado($os);
            }
            SlaService::avancar($os);
        });
    }

    private function seguro(callable $acao): void
    {
        try {
            $acao();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
