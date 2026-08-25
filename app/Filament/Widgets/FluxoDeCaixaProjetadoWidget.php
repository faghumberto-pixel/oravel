<?php

namespace App\Filament\Widgets;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Pedido do usuário 2026-08-25 (item 4, último do roteiro de artefatos
 * comerciais): "sei, num único lugar, quanto vou receber e quanto vou
 * pagar nos próximos 30/60/90 dias, sem juntar planilha de cada setor?".
 *
 * Antes desta tela, AccountReceivableStats e AccountPayableStats existiam
 * soltos, cada um só na sua própria página de listagem -- nunca lado a
 * lado, e sem nenhum agrupamento por janela de vencimento nem saldo
 * (receber - pagar). Este widget é a composição das duas pontas.
 *
 * Só considera valores ainda em aberto (pendente/atrasado) -- contas já
 * pagas/recebidas não entram na projeção, senão o "saldo projetado"
 * misturaria passado com futuro.
 */
class FluxoDeCaixaProjetadoWidget extends BaseWidget
{
    protected static ?int $sort = -5;

    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) Tenancy::current()
            && $user
            && ($user->isAdmin() || $user->can('ler_contas_receber') || $user->can('ler_contas_pagar'));
    }

    protected function getStats(): array
    {
        $hoje = Carbon::today();
        $janelas = [30 => now()->addDays(30), 60 => now()->addDays(60), 90 => now()->addDays(90)];

        $emAberto = fn ($query) => $query->whereIn('status', ['pendente', 'atrasado']);

        $totalReceber = $emAberto(AccountReceivable::query())->sum('amount');
        $totalPagar = $emAberto(AccountPayable::query())->sum('amount');
        $saldoProjetado = $totalReceber - $totalPagar;

        $stats = [
            Stat::make('A Receber (em aberto)', 'R$ '.number_format((float) $totalReceber, 2, ',', '.'))
                ->description('Pendente + atrasado, todas as janelas')
                ->color('success'),

            Stat::make('A Pagar (em aberto)', 'R$ '.number_format((float) $totalPagar, 2, ',', '.'))
                ->description('Pendente + atrasado, todas as janelas')
                ->color('danger'),

            Stat::make('Saldo Projetado', 'R$ '.number_format((float) $saldoProjetado, 2, ',', '.'))
                ->description($saldoProjetado >= 0 ? 'A receber supera a pagar' : 'A pagar supera a receber')
                ->color($saldoProjetado >= 0 ? 'success' : 'danger'),
        ];

        foreach ($janelas as $dias => $limite) {
            $receberJanela = $emAberto(AccountReceivable::query())
                ->whereBetween('due_date', [$hoje, $limite])->sum('amount');
            $pagarJanela = $emAberto(AccountPayable::query())
                ->whereBetween('due_date', [$hoje, $limite])->sum('amount');
            $saldoJanela = $receberJanela - $pagarJanela;

            $stats[] = Stat::make("Próximos {$dias} dias", 'R$ '.number_format((float) $saldoJanela, 2, ',', '.'))
                ->description('Receber R$ '.number_format((float) $receberJanela, 2, ',', '.').' — Pagar R$ '.number_format((float) $pagarJanela, 2, ',', '.'))
                ->color($saldoJanela >= 0 ? 'success' : 'danger');
        }

        return $stats;
    }
}
