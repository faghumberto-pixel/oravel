<?php

namespace App\Console\Commands;

use App\Models\EmployeeCertification;
use App\Models\EquipmentAllocation;
use App\Models\User;
use App\Notifications\EmployeeCertificationExpiringNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Varre certificacoes NR vencendo em 30/15/7 dias (alerta) ou ja vencidas
 * (alerta + bloqueio retroativo), mesmo padrao de VerificarVencimentosCommand
 * (financeiro:verificar-vencimentos). O caso que a trigger de banco sozinha
 * nao cobre: uma certificacao que ERA valida no momento da alocacao e
 * venceu depois, com o colaborador ja alocado -- aqui a gente força o
 * re-save da alocacao pra trigger reavaliar e bloquear se for o caso.
 */
class CheckEmployeeCertificationExpirations extends Command
{
    protected $signature = 'employees:check-certification-expirations';

    protected $description = 'Notifica certificações NR vencendo (30/15/7 dias) ou vencidas, e propaga bloqueio pras alocações que dependiam delas.';

    public function handle(): int
    {
        $totalNotificados = 0;

        $vencidas = EmployeeCertification::whereDate('data_validade', '<', now())
            ->whereDate('data_validade', '>=', now()->subDays(3))
            ->get();

        foreach ($vencidas as $certification) {
            $this->notifyTenant($certification, 'vencida');
            $totalNotificados++;

            // Re-save força a trigger de equipment_allocations a reavaliar
            // -- se essa era a unica certificacao valida sustentando uma
            // alocacao ativa, ela vira bloqueada agora.
            EquipmentAllocation::where('employee_id', $certification->employee_id)
                ->where('blocked', false)
                ->each(fn (EquipmentAllocation $allocation) => $allocation->save());
        }

        foreach ([7 => 'vencendo_7d', 15 => 'vencendo_15d', 30 => 'vencendo_30d'] as $dias => $tipo) {
            $vencendo = EmployeeCertification::whereDate('data_validade', now()->addDays($dias))->get();

            foreach ($vencendo as $certification) {
                $this->notifyTenant($certification, $tipo);
                $totalNotificados++;
            }
        }

        $this->info("Notificações geradas: {$totalNotificados}.");

        return self::SUCCESS;
    }

    private function notifyTenant(EmployeeCertification $certification, string $tipo): void
    {
        $usuarios = User::where('tenant_id', $certification->tenant_id)->get();

        if ($usuarios->isEmpty()) {
            return;
        }

        Notification::send($usuarios, new EmployeeCertificationExpiringNotification($certification, $tipo));
    }
}
