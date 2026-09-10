<?php

namespace App\Console\Commands;

use App\Models\EpiDelivery;
use App\Models\EpiSpecification;
use App\Models\User;
use App\Notifications\EpiCaExpiringNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Varre CAs de EPI vencendo em 30/15/7 dias (alerta) ou ja vencidos
 * (alerta + bloqueio retroativo), mesmo padrao de
 * CheckEmployeeCertificationExpirations. O caso que a trigger de banco
 * sozinha nao cobre: um EPI cujo CA ERA valido no momento da entrega e
 * venceu depois, com o colaborador ja em posse dele -- aqui a gente
 * força o re-save das entregas ativas pra trigger reavaliar e marcar
 * `blocked` (em Postgres; sem efeito em sqlite/testes, ver guard na
 * migration).
 */
class CheckEpiCaExpirations extends Command
{
    protected $signature = 'epi:check-ca-expirations';

    protected $description = 'Notifica CAs de EPI vencendo (30/15/7 dias) ou vencidos, e propaga bloqueio pras entregas ativas que dependiam deles.';

    public function handle(): int
    {
        $totalNotificados = 0;

        $vencidos = EpiSpecification::whereDate('ca_validade', '<', now())
            ->whereDate('ca_validade', '>=', now()->subDays(3))
            ->get();

        foreach ($vencidos as $specification) {
            $this->notifyTenant($specification, 'vencida');
            $totalNotificados++;

            // Re-save força a trigger de epi_deliveries a reavaliar -- se
            // o CA deste material acabou de vencer, entregas ativas dele
            // passam a aparecer bloqueadas (dado que alimenta o dashboard
            // de "EPI vencido em uso").
            EpiDelivery::where('material_id', $specification->material_id)
                ->where('status', EpiDelivery::STATUS_ATIVO)
                ->where('blocked', false)
                ->each(fn (EpiDelivery $delivery) => $delivery->save());
        }

        foreach ([7 => 'vencendo_7d', 15 => 'vencendo_15d', 30 => 'vencendo_30d'] as $dias => $tipo) {
            $vencendo = EpiSpecification::whereDate('ca_validade', now()->addDays($dias))->get();

            foreach ($vencendo as $specification) {
                $this->notifyTenant($specification, $tipo);
                $totalNotificados++;
            }
        }

        $this->info("Notificações geradas: {$totalNotificados}.");

        return self::SUCCESS;
    }

    private function notifyTenant(EpiSpecification $specification, string $tipo): void
    {
        $usuarios = User::where('tenant_id', $specification->tenant_id)->get();

        if ($usuarios->isEmpty()) {
            return;
        }

        Notification::send($usuarios, new EpiCaExpiringNotification($specification, $tipo));
    }
}
