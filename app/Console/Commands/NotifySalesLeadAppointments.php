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
 * (nao repete alerta antes de 15 min), mesmo espirito de
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
            ->where(fn ($q) => $q->whereNull('last_alerted_at')
                ->orWhere('last_alerted_at', '<=', now()->subMinutes(15)))
            ->with('lead', 'assignedUser')
            ->get();

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
                    ->sendToDatabase($recipient);
            }

            $appointment->update(['last_alerted_at' => now()]);
        }

        $this->info("Alertas enviados para {$appointments->count()} compromisso(s).");

        return self::SUCCESS;
    }
}
