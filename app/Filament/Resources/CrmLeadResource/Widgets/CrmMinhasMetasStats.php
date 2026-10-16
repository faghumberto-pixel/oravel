<?php

namespace App\Filament\Resources\CrmLeadResource\Widgets;

use App\Models\CrmMeta;
use App\Services\CrmMetasService;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** "Minhas metas do mês" no cabeçalho da lista de leads (só aparece se o usuário pode ver metas e há meta). */
class CrmMinhasMetasStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) (Tenancy::current() && auth()->user()?->can('viewAny', CrmMeta::class));
    }

    protected function getStats(): array
    {
        $d = app(CrmMetasService::class)->indicadores((string) Tenancy::current()->id, auth()->id(), now());

        if (! $d['tem_meta']) {
            return [Stat::make('Minhas metas do mês', 'sem meta')->description('Peça ao gestor para definir')];
        }

        $linha = fn (string $ind, string $rotulo, string $fmt) => Stat::make($rotulo, $fmt)
            ->description($d['atingimento'][$ind] === null ? 'sem meta' : number_format($d['atingimento'][$ind], 0, ',', '.').'% da meta')
            ->color($d['atingimento'][$ind] === null ? 'gray' : ($d['atingimento'][$ind] >= 100 ? 'success' : ($d['atingimento'][$ind] >= 60 ? 'warning' : 'danger')));

        return [
            $linha('valor', 'Vendido no mês', 'R$ '.number_format($d['realizado']['valor'], 2, ',', '.')),
            $linha('ganhos', 'Negócios ganhos', (string) $d['realizado']['ganhos']),
            $linha('visitas', 'Visitas realizadas', (string) $d['realizado']['visitas']),
        ];
    }
}
