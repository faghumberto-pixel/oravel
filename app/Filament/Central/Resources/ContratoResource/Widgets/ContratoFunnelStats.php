<?php

namespace App\Filament\Central\Resources\ContratoResource\Widgets;

use App\Filament\Central\Resources\TenantResource;
use App\Models\DocumentSignature;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Cards de resumo no topo da tela de Contratos (pedido do usuário
 * 2026-09-23: "crie cards na parte superior com links para contratos
 * enviados, contratos assinados, contratos nao assinado, Pagos, Em
 * aberto"). Cada card é clicável e leva pra listagem já filtrada -- mesmo
 * padrão já usado em SalesLeadListStats/MaintenancePlanStats.
 *
 * "Contratos" aqui = DocumentSignature do tipo Tenant (Contrato de
 * Assinatura), não Contract/OS -- é sobre o funil de assinatura da
 * própria Oravel, que é o assunto desta tela. Os 3 primeiros cards
 * levam pra Assinaturas Eletrônicas (painel admin, filtrado por tipo +
 * status); os 2 últimos levam pra Tenants (Central), filtrado por
 * status de pagamento -- por isso usam url() plana em vez do helper de
 * Resource, já que é um link pra outro painel (admin), não pra este
 * (central).
 */
class ContratoFunnelStats extends BaseWidget
{
    protected function getStats(): array
    {
        $baseQuery = DocumentSignature::where('signable_type', Tenant::class);

        $enviados = (clone $baseQuery)->count();
        $assinados = (clone $baseQuery)->where('status', 'signed')->count();
        $naoAssinados = $enviados - $assinados;

        $pagos = Tenant::where('asaas_payment_status', Tenant::PAYMENT_STATUS_EM_DIA)->count();
        $emAberto = Tenant::where('asaas_payment_status', Tenant::PAYMENT_STATUS_ATRASADO)->count();

        return [
            Stat::make('Contratos Enviados', $enviados)
                ->description('Links de contrato gerados no total')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('gray')
                ->url($this->documentSignatureUrl()),

            Stat::make('Contratos Assinados', $assinados)
                ->description('Já assinaram o contrato')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->url($this->documentSignatureUrl('signed')),

            Stat::make('Contratos Não Assinados', $naoAssinados)
                ->description('Aguardando o cliente assinar')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->url($this->documentSignatureUrl('pending')),

            Stat::make('Pagos', $pagos)
                ->description('Assinatura SaaS em dia')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->url(TenantResource::getUrl('index', ['tableFilters[asaas_payment_status][value]' => Tenant::PAYMENT_STATUS_EM_DIA])),

            Stat::make('Em Aberto', $emAberto)
                ->description('Cobrança atrasada')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->url(TenantResource::getUrl('index', ['tableFilters[asaas_payment_status][value]' => Tenant::PAYMENT_STATUS_ATRASADO])),
        ];
    }

    private function documentSignatureUrl(?string $status = null): string
    {
        $query = ['tableFilters[signable_type][value]' => Tenant::class];

        if ($status) {
            $query['tableFilters[status][value]'] = $status;
        }

        return url('/admin/document-signatures').'?'.http_build_query($query);
    }
}
