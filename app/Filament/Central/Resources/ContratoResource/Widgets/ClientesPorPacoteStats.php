<?php

namespace App\Filament\Central\Resources\ContratoResource\Widgets;

use App\Models\Tenant;
use App\Support\Pacotes;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Quantos clientes (empresas) há em cada pacote: Locadora, CRM, Estoque, Logística e Personalizado. */
class ClientesPorPacoteStats extends BaseWidget
{
    protected function getStats(): array
    {
        $contagem = Tenant::query()->join('plans', 'plans.id', '=', 'tenants.plan_id')
            ->selectRaw('plans.pacote as pacote, count(*) as total')->groupBy('plans.pacote')->pluck('total', 'pacote');

        $stats = [];

        foreach (Pacotes::opcoes() as $codigo => $nome) {
            $stats[] = Stat::make("Clientes — {$nome}", (int) ($contagem[$codigo] ?? 0))->color('primary');
        }

        $stats[] = Stat::make('Clientes — Personalizado', (int) ($contagem[''] ?? $contagem[null] ?? 0))->color('gray');

        return $stats;
    }
}
