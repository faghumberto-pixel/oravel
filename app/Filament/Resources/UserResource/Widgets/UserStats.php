<?php

namespace App\Filament\Resources\UserResource\Widgets;

use App\Models\User;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = User::count();
        $tecnicos = User::where('role', 'tecnico')->count();
        $admins = User::where('role', 'admin')->count();

        $ativosNaSemana = User::where('last_seen', '>=', now()->subDays(7))->count();

        return [
            Stat::make('Total de Funcionários', $total)
                ->description('Cadastrados no tenant')
                ->color('gray'),

            Stat::make('Técnicos', $tecnicos)
                ->description('Equipe de campo')
                ->color('info'),

            Stat::make('Administradores', $admins)
                ->description('Acesso total ao tenant')
                ->color('warning'),

            Stat::make('Ativos na Última Semana', $ativosNaSemana)
                ->description('Login/atividade recente')
                ->color('success'),
        ];
    }
}
