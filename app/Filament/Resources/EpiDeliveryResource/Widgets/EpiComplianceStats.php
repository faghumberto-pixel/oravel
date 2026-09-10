<?php

namespace App\Filament\Resources\EpiDeliveryResource\Widgets;

use App\Models\EpiDelivery;
use App\Models\EpiSpecification;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Painel de conformidade NR-6: CAs vencidos, EPIs em uso com CA vencido,
 * vida util estourada e devolucoes atrasadas -- as 4 metricas que o
 * pedido original descreveu como "dashboard de EPIs vencidos/vencendo".
 */
class EpiComplianceStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $caVencidos = EpiSpecification::whereDate('ca_validade', '<', now())->count();

        $entregasComCaVencido = EpiDelivery::where('status', EpiDelivery::STATUS_ATIVO)
            ->where('blocked', true)
            ->count();

        $vidaUtilEstourada = EpiDelivery::query()
            ->where('status', EpiDelivery::STATUS_ATIVO)
            ->whereHas('material.epiSpecification', fn ($query) => $query->whereNotNull('estimated_lifespan_days'))
            ->with('material.epiSpecification')
            ->get()
            ->filter(fn (EpiDelivery $delivery) => $delivery->isLifespanExceeded())
            ->count();

        $devolucoesAtrasadas = EpiDelivery::where('ownership_mode', EpiSpecification::OWNERSHIP_EMPRESTIMO_TEMPORARIO)
            ->where('status', EpiDelivery::STATUS_ATIVO)
            ->whereNull('returned_at')
            ->whereDate('expected_return_at', '<', now())
            ->count();

        return [
            Stat::make('CAs de EPI Vencidos', $caVencidos)
                ->description('Itens do catálogo com Certificado de Aprovação vencido')
                ->color($caVencidos > 0 ? 'danger' : 'success'),

            Stat::make('EPIs em Uso com CA Vencido', $entregasComCaVencido)
                ->description('Colaboradores usando EPI cujo CA venceu depois da entrega')
                ->color($entregasComCaVencido > 0 ? 'danger' : 'success'),

            Stat::make('Vida Útil Estourada', $vidaUtilEstourada)
                ->description('Tempo de uso recomendado ultrapassado, sugerir troca')
                ->color($vidaUtilEstourada > 0 ? 'warning' : 'success'),

            Stat::make('Devoluções Atrasadas', $devolucoesAtrasadas)
                ->description('Empréstimos temporários além da data prevista')
                ->color($devolucoesAtrasadas > 0 ? 'warning' : 'success'),
        ];
    }
}
