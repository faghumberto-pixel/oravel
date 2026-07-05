<?php

namespace App\Filament\Resources\PartsRequestResource\Widgets;

use App\Models\PartsRequest;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PartsRequestStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = PartsRequest::count();
        $pendentes = PartsRequest::where('status', 'pendente')->count();
        $pedidas = PartsRequest::where('status', 'pedida')->count();

        $entreguesNoMes = PartsRequest::where('status', 'entregue')
            ->where('updated_at', '>=', now()->startOfMonth())
            ->count();

        $custoNoMes = PartsRequest::where('status', 'entregue')
            ->where('updated_at', '>=', now()->startOfMonth())
            ->sum('cost_at_time');

        return [
            Stat::make('Total de Solicitações', $total)
                ->description('Todos os status')
                ->color('gray'),

            Stat::make('Pendentes', $pendentes)
                ->description('Aguardando compra')
                ->color($pendentes > 0 ? 'danger' : 'success'),

            Stat::make('Compradas / Pedidas', $pedidas)
                ->description('Em trânsito para o técnico')
                ->color('warning'),

            Stat::make('Custo em Peças no Mês', 'R$ '.number_format((float) $custoNoMes, 2, ',', '.'))
                ->description($entreguesNoMes.' entregues em '.now()->translatedFormat('F/Y'))
                ->color('info'),
        ];
    }
}
