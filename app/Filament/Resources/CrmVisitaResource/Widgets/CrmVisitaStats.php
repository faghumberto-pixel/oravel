<?php

namespace App\Filament\Resources\CrmVisitaResource\Widgets;

use App\Filament\Resources\CrmVisitaResource;
use App\Models\CrmVisita;
use App\Models\CrmVisitaDespesa;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/** Totais do mês corrente (respeitam a visibilidade: vendedor vê só os dele). */
class CrmVisitaStats extends BaseWidget
{
    protected function getStats(): array
    {
        $inicio = now()->startOfMonth();
        $visitas = CrmVisitaResource::getEloquentQuery()->where('agendada_para', '>=', $inicio);
        $user = auth()->user();

        $realizadas = (clone $visitas)->where('status', CrmVisita::STATUS_REALIZADA)->count();
        $km = (int) (clone $visitas)->where('status', CrmVisita::STATUS_REALIZADA)
            ->sum(DB::raw('GREATEST(coalesce(km_fim,0) - coalesce(km_inicio,0), 0)'));

        $despesas = CrmVisitaDespesa::query()->where('data', '>=', $inicio->toDateString())
            ->when($user && ! $user->canSeeAllCrmLeads(), fn ($q) => $q->where('user_id', $user->id))
            ->sum('valor');

        return [
            Stat::make('Visitas realizadas no mês', $realizadas)->description('Agendadas no total: '.(clone $visitas)->count()),
            Stat::make('Km rodados no mês', number_format($km, 0, ',', '.'))->description('Soma dos km das visitas realizadas'),
            Stat::make('Despesas de campo no mês', 'R$ '.number_format((float) $despesas, 2, ',', '.')),
        ];
    }
}
