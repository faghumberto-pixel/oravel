<?php

namespace App\Console\Commands;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Pedido do usuário 2026-08-25 (item 4 do roteiro): status "atrasado" era
 * sempre manual -- ninguém marcava sozinho quando a data já tinha passado.
 * VerificarVencimentosCommand calcula "atrasada" só pra decidir o texto da
 * notificação, nunca grava de volta no banco -- uma conta vencida
 * continuava "pendente" pra sempre nos StatsOverview/filtros até alguém
 * abrir a tela e mudar manualmente. Este comando fecha essa lacuna.
 */
class MarcarContasAtrasadas extends Command
{
    protected $signature = 'financeiro:marcar-contas-atrasadas';

    protected $description = 'Marca como atrasado contas a pagar/receber pendentes cuja data de vencimento já passou';

    public function handle(): int
    {
        $hoje = Carbon::today();

        $receiveis = AccountReceivable::where('status', 'pendente')
            ->whereDate('due_date', '<', $hoje)
            ->update(['status' => 'atrasado']);

        $payaveis = AccountPayable::where('status', 'pendente')
            ->whereDate('due_date', '<', $hoje)
            ->update(['status' => 'atrasado']);

        $this->info("{$receiveis} conta(s) a receber e {$payaveis} conta(s) a pagar marcadas como atrasadas.");

        return Command::SUCCESS;
    }
}
