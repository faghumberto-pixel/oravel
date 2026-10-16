<?php

namespace App\Filament\Resources\CrmLeadResource\Widgets;

use App\Models\CrmEtapa;
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

        // Em andamento = etapas abertas depois da primeira do funil (a primeira é a entrada, "Novo").
        $emAndamento = CrmLead::whereHas('etapa', fn ($q) => $q->where('tipo', CrmEtapa::TIPO_ABERTA)->where('ordem', '>', 0))->count();

        $convertidos = CrmLead::where('stage', CrmLead::STAGE_CONVERTIDO)->count();

        $perdidos = CrmLead::where('stage', CrmLead::STAGE_PERDIDO)->count();

        return [
            Stat::make('Total de Leads', $total)
                ->description('Cadastrados no funil')
                ->color('gray'),

            Stat::make('Em Andamento', $emAndamento)
                ->description('Já em contato, antes de fechar')
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
