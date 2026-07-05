<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RadarUrgencia extends BaseWidget
{
    /**
     * Impede que o widget tente renderizar se não houver um tenant ativo.
     * Essencial para evitar erros em telas de login ou fora do contexto de tenant.
     */
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    /**
     * Define as estatísticas exibidas no painel.
     *
     * "atrasado"/"proxima" nunca foram valores reais de MaintenanceOrder.status
     * (nada no sistema os atribui) -- os stats abaixo usam os status/campos
     * reais em uso (Aberto/Pendente/Em Andamento, maintenance_type, created_at).
     * O filtro de tenant fica só por conta do global scope (BelongsToTenant),
     * que já bypassa corretamente para super admin -- filtrar de novo aqui
     * manualmente por Tenancy::current() escondia os dados de super admin.
     */
    protected function getStats(): array
    {
        if (! Tenancy::current()) {
            return [];
        }

        return [
            Stat::make('Manutenção em Atraso',
                MaintenanceOrder::whereIn('status', ['Aberto', 'Pendente', 'Em Andamento'])
                    ->where('created_at', '<', now()->subDays(3))
                    ->count()
            )
                ->description('O.S. abertas há mais de 3 dias')
                ->color('danger'),

            Stat::make('Preventiva Próxima',
                MaintenanceOrder::where('maintenance_type', 'Preventiva')
                    ->where('status', 'Aberto')
                    ->count()
            )
                ->description('Preventivas ainda não iniciadas')
                ->color('warning'),

            Stat::make('Disponível no Pátio',
                Asset::where('status', 'disponivel')->count()
            )
                ->description('Pronto para despacho')
                ->color('success'),
        ];
    }
}
