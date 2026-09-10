<?php

namespace App\Console\Commands;

use App\Models\EpiDelivery;
use App\Models\User;
use App\Notifications\EpiLifespanExpiringNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Varre entregas de EPI ativas cuja vida util estimada (separada da
 * validade do CA) esta vencendo em 30/15/7 dias ou ja estourou. So'
 * sugere troca -- nunca bloqueia (diferente de CheckEpiCaExpirations,
 * onde a trigger de banco bloqueia de verdade). Cada entrega tem sua
 * propria data de vencimento (delivered_at + estimated_lifespan_days),
 * por isso o filtro roda em memoria em vez de um whereDate direto.
 */
class CheckEpiLifespanExpirations extends Command
{
    protected $signature = 'epi:check-lifespan-expirations';

    protected $description = 'Notifica EPIs entregues cuja vida útil recomendada está vencendo (30/15/7 dias) ou já foi ultrapassada.';

    public function handle(): int
    {
        $totalNotificados = 0;

        $entregasAtivas = EpiDelivery::query()
            ->where('status', EpiDelivery::STATUS_ATIVO)
            ->whereHas('material.epiSpecification', fn ($query) => $query->whereNotNull('estimated_lifespan_days'))
            ->with(['material.epiSpecification', 'employee'])
            ->get();

        $janelas = [
            'vencida' => fn ($vencimento) => $vencimento->isPast() && $vencimento->greaterThanOrEqualTo(now()->subDays(3)),
            'vencendo_7d' => fn ($vencimento) => $vencimento->isSameDay(now()->addDays(7)),
            'vencendo_15d' => fn ($vencimento) => $vencimento->isSameDay(now()->addDays(15)),
            'vencendo_30d' => fn ($vencimento) => $vencimento->isSameDay(now()->addDays(30)),
        ];

        foreach ($entregasAtivas as $delivery) {
            $lifespan = $delivery->material->epiSpecification->estimated_lifespan_days;
            $vencimento = $delivery->delivered_at->copy()->addDays($lifespan);

            foreach ($janelas as $tipo => $matches) {
                if ($matches($vencimento)) {
                    $this->notifyTenant($delivery, $tipo);
                    $totalNotificados++;
                    break;
                }
            }
        }

        $this->info("Notificações geradas: {$totalNotificados}.");

        return self::SUCCESS;
    }

    private function notifyTenant(EpiDelivery $delivery, string $tipo): void
    {
        $usuarios = User::where('tenant_id', $delivery->tenant_id)->get();

        if ($usuarios->isEmpty()) {
            return;
        }

        Notification::send($usuarios, new EpiLifespanExpiringNotification($delivery, $tipo));
    }
}
