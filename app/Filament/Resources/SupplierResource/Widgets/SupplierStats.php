<?php

namespace App\Filament\Resources\SupplierResource\Widgets;

use App\Models\Supplier;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SupplierStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = Supplier::count();

        $complianceCompleto = Supplier::where('compliance_ceis_cnep', true)
            ->where('lista_trabalho_escravo', true)
            ->where('termo_lgpd', true)
            ->count();

        $materiaisVinculados = Supplier::withCount('materials')->get()->sum('materials_count');

        $pendentesDocumentacao = Supplier::where(function ($q) {
            $q->whereNull('cnd_federal')->orWhereNull('crf_fgts')->orWhereNull('cndt');
        })->count();

        return [
            Stat::make('Total de Fornecedores', $total)
                ->description('Cadastrados no tenant')
                ->color('gray'),

            Stat::make('Compliance Completo', $complianceCompleto)
                ->description('CEIS/CNEP + Trabalho Escravo + LGPD')
                ->color('success'),

            Stat::make('Materiais Vinculados', $materiaisVinculados)
                ->description('Homologados para compra')
                ->color('info'),

            Stat::make('Pendentes de Certidão', $pendentesDocumentacao)
                ->description('CND Federal, FGTS ou CNDT faltando')
                ->color($pendentesDocumentacao > 0 ? 'warning' : 'success'),
        ];
    }
}
