<?php

namespace App\Filament\Resources\ClientResource\Widgets;

use App\Models\Client;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClientStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = Client::count();

        $comContratoAtivo = Client::whereHas('contracts', fn ($q) => $q->where('status', 'Ativo'))->count();

        $novosNoMes = Client::where('created_at', '>=', now()->startOfMonth())->count();

        $semContrato = Client::whereDoesntHave('contracts')->count();

        return [
            Stat::make('Total de Clientes', $total)
                ->description('Cadastrados no tenant')
                ->color('gray'),

            Stat::make('Com Contrato Ativo', $comContratoAtivo)
                ->description('Locação em andamento')
                ->color('success'),

            Stat::make('Novos no Mês', $novosNoMes)
                ->description(now()->translatedFormat('F/Y'))
                ->color('info'),

            Stat::make('Sem Nenhum Contrato', $semContrato)
                ->description('Oportunidade comercial')
                ->color('warning'),
        ];
    }
}
