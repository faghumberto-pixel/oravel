<?php

namespace App\Filament\Resources\CrmLeadResource\Widgets;

use App\Models\CrmLead;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Mesmo padrão já usado em Assets/Clients/Materials/Fornecedores
 * (App\Filament\Resources\{Resource}\Widgets\{Resource}Stats) -- só
 * faltava em Leads.
 */
class CrmLeadStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = CrmLead::count();

        $emAndamento = CrmLead::whereIn('stage', [
            CrmLead::STAGE_CONTATO_INICIADO,
            CrmLead::STAGE_QUALIFICADO,
        ])->count();

        $convertidos = CrmLead::where('stage', CrmLead::STAGE_CONVERTIDO)->count();

        $perdidos = CrmLead::where('stage', CrmLead::STAGE_PERDIDO)->count();

        return [
            Stat::make('Total de Leads', $total)
                ->description('Cadastrados no funil')
                ->color('gray'),

            Stat::make('Em Andamento', $emAndamento)
                ->description('Contato iniciado ou qualificado')
                ->color('info'),

            Stat::make('Convertidos', $convertidos)
                ->description('Viraram cliente')
                ->color('success'),

            Stat::make('Perdidos', $perdidos)
                ->description('Funil encerrado sem conversão')
                ->color($perdidos > 0 ? 'warning' : 'success'),
        ];
    }
}
