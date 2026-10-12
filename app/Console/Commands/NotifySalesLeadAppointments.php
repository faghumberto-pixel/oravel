<?php

namespace App\Console\Commands;

use App\Models\SalesLeadAppointment;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

/**
 * Roda em intervalo curto (App\Console\Kernel::schedule(), a cada 5 min --
 * pedido explicito do usuario) avisando sobre compromissos do CRM
 * comercial (tela "Programacao") que estao vencidos ou proximos e ainda
 * nao foram concluidos. Dedupe via last_alerted_at no proprio compromisso
 * (1 aviso "em breve" e 1 "vencido"), mesmo espirito de
 * CheckMaintenanceDueAlerts::MaintenanceDueAlert, sem precisar de tabela
 * separada aqui.
 */
class NotifySalesLeadAppointments extends Command
{
    protected $signature = 'sales:notify-appointments';

    protected $description = 'Avisa sobre compromissos do CRM comercial vencidos ou proximos (nao concluidos)';

    public function handle(): int
    {
        $appointments = SalesLeadAppointment::query()
            ->whereIn('status', [
                SalesLeadAppointment::STATUS_PENDENTE,
                SalesLeadAppointment::STATUS_AGUARDANDO,
                SalesLeadAppointment::STATUS_EM_ANDAMENTO,
            ])
            ->where('scheduled_at', '<=', now()->addMinutes(30))
            // Cada compromisso avisa no máximo 2x: "em breve" (1ª vez) e "vencido" (1x, quando passa
            // da hora). Antes repetia "vencido" a cada 15 min sem parar (504 avisos em PROD, 12/10/2026).
            // Reagendar para depois do último aviso volta a avisar.
            ->where(fn ($q) => $q->whereNull('last_alerted_at')
                ->orWhereColumn('last_alerted_at', '<', 'scheduled_at'))
            ->with('lead', 'assignedUser')
            ->get()
            ->filter(fn ($a) => $a->last_alerted_at === null || $a->scheduled_at->isPast());

        foreach ($appointments as $appointment) {
            $isOverdue = $appointment->scheduled_at->isPast();

            $recipients = $appointment->assignedUser
                ? collect([$appointment->assignedUser])
                : User::whereIn('email', config('oravel.super_admins', []))->get();

            foreach ($recipients as $recipient) {
                Notification::make()
                    ->title($isOverdue ? 'Compromisso vencido: '.$appointment->title : 'Compromisso em breve: '.$appointment->title)
                    ->body(sprintf(
                        '%s — %s%s',
                        $appointment->lead?->company_name ?? 'Lead',
                        $appointment->scheduled_at->format('d/m/Y H:i'),
                        $isOverdue ? ' (venceu)' : ''
                    ))
                    ->icon('heroicon-o-calendar-days')
                    ->iconColor($isOverdue ? 'danger' : 'warning')
                    ->viewData(['scope' => 'central']) // vendas da Oravel (Central): só no sino da Central
                    ->sendToDatabase($recipient);
            }

            $appointment->update(['last_alerted_at' => now()]);
        }

        $this->info("Alertas enviados para {$appointments->count()} compromisso(s).");

        return self::SUCCESS;
    }
}
