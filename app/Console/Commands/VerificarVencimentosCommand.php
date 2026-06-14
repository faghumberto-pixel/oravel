<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Notifications\ContaPagarNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

class VerificarVencimentosCommand extends Command
{
    protected $signature = 'financeiro:verificar-vencimentos';
    protected $description = 'Verifica contas vencendo hoje, em breve e atrasadas por empresa';

    public function handle()
    {
        $modelClass = '\App\Models\AccountPayable';

        if (!class_exists($modelClass)) {
            $this->error('Erro: O Model \App\Models\AccountPayable não foi encontrado.');
            return Command::FAILURE;
        }

        $hoje = Carbon::today();
        $totalNotificados = 0;

        // 🚨 FILTRO CORRIGIDO: Busca estritamente o status 'pendente' minúsculo que está no banco
        $contas = $modelClass::where('status', 'pendente')
            ->whereBetween('due_date', [Carbon::today()->subDays(30), Carbon::today()->addDays(7)])
            ->get();

        if ($contas->isEmpty()) {
            $this->warn('Nenhuma conta com status "pendente" foi localizada no período.');
            return Command::SUCCESS;
        }

        foreach ($contas as $conta) {
            // Busca todos os usuários associados ao tenant_id da conta
            $usuariosDoTenant = User::where('tenant_id', $conta->tenant_id)->get();

            if ($usuariosDoTenant->isEmpty()) {
                continue;
            }

            // Define o tipo com base no vencimento
            $dueDate = Carbon::parse($conta->due_date);
            if ($dueDate->isToday()) {
                $tipo = 'vencimento_hoje';
            } elseif ($dueDate->isPast()) {
                $tipo = 'atrasada';
            } else {
                $tipo = 'vencendo_breve';
            }

            // Dispara a notificação para os usuários da empresa
            Notification::send($usuariosDoTenant, new ContaPagarNotification($conta, $tipo));
            $totalNotificados += $usuariosDoTenant->count();
        }

        $this->info("Sucesso! {$totalNotificados} notificações geradas no banco de dados.");
        return Command::SUCCESS;
    }
}