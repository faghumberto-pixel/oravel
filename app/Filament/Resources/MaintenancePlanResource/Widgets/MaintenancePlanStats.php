<?php

namespace App\Filament\Resources\MaintenancePlanResource\Widgets;

use App\Filament\Resources\MaintenancePlanResource\Support\PlanStatus;
use App\Models\MaintenancePlan;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * 3 cards de status por PLANO (Vencidos / A Vencer / Dentro do Prazo),
 * clicáveis -- cada um aplica o filtro plan_status da tabela via
 * querystring (?tableFilters[plan_status][value]=vencido), mesmo mecanismo
 * usado pelo botão Imprimir de MaintenancePlanResource pra respeitar o
 * filtro atual. Substituiu os 4 stats genéricos anteriores (pedido do
 * usuário 2026-08-30: cards clicáveis que filtram a lista).
 */
class MaintenancePlanStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    /**
     * Todo Filament\Widgets\Widget é lazy por padrão (Filament\Support\
     * Concerns\CanBeLazy, $isLazy=true) -- carrega o conteúdo real via uma
     * requisição Livewire separada depois do placeholder inicial. Desligado
     * aqui: os cards precisam aparecer já na 1ª resposta (mesmo padrão de
     * AssetStatusChartWidget).
     */
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $planos = MaintenancePlan::with(['asset', 'checklistGroup'])->where('is_active', true)->get();

        $porStatus = $planos->groupBy(fn (MaintenancePlan $plan) => PlanStatus::forPlan($plan));

        $vencidos = $porStatus->get('vencido', collect())->count();
        $aVencer = $porStatus->get('a_vencer', collect())->count();
        $dentroDoPrazo = $porStatus->get('dentro_do_prazo', collect())->count();

        return [
            Stat::make('Vencidos', $vencidos)
                ->description('Planos com item de preventiva vencido')
                ->color($vencidos > 0 ? 'danger' : 'success')
                ->url(static::filterUrl('vencido')),

            Stat::make('A Vencer', $aVencer)
                ->description('Vence dentro deste mês')
                ->color($aVencer > 0 ? 'warning' : 'success')
                ->url(static::filterUrl('a_vencer')),

            Stat::make('Dentro do Prazo', $dentroDoPrazo)
                ->description('Sem vencimento próximo')
                ->color('success')
                ->url(static::filterUrl('dentro_do_prazo')),
        ];
    }

    private static function filterUrl(string $status): string
    {
        return route('filament.admin.resources.maintenance-plans.index', [
            'tableFilters' => ['plan_status' => ['value' => $status]],
        ]);
    }
}
