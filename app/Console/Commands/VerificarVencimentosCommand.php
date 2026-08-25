<?php

namespace App\Console\Commands;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\User;
use App\Notifications\ContaPagarNotification;
use App\Notifications\ContaReceberNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class VerificarVencimentosCommand extends Command
{
    protected $signature = 'financeiro:verificar-vencimentos';

    protected $description = 'Verifica contas a pagar e a receber vencendo hoje, em breve e atrasadas por empresa';

    public function handle()
    {
        // Pedido do usuário 2026-08-25 (item 4 do roteiro): antes este
        // comando só cobria AccountPayable -- o único alerta proativo de
        // vencimento do sistema nunca avisava sobre contas a receber.
        $totalNotificados = $this->processar(AccountPayable::class, ContaPagarNotification::class);
        $totalNotificados += $this->processar(AccountReceivable::class, ContaReceberNotification::class);

        $this->info("Sucesso! {$totalNotificados} notificações geradas no banco de dados.");

        return Command::SUCCESS;
    }

    private function processar(string $modelClass, string $notificationClass): int
    {
        $contas = $modelClass::where('status', 'pendente')
            ->whereBetween('due_date', [Carbon::today()->subDays(30), Carbon::today()->addDays(7)])
            ->get();

        $totalNotificados = 0;

        foreach ($contas as $conta) {
            $usuariosDoTenant = User::where('tenant_id', $conta->tenant_id)->get();

            if ($usuariosDoTenant->isEmpty()) {
                continue;
            }

            $dueDate = Carbon::parse($conta->due_date);
            if ($dueDate->isToday()) {
                $tipo = 'vencimento_hoje';
            } elseif ($dueDate->isPast()) {
                $tipo = 'atrasada';
            } else {
                $tipo = 'vencendo_breve';
            }

            Notification::send($usuariosDoTenant, new $notificationClass($conta, $tipo));
            $totalNotificados += $usuariosDoTenant->count();
        }

        return $totalNotificados;
    }
}