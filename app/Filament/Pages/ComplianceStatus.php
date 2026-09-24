<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Status de conformidade da PRÓPRIA assinatura Oravel (contrato SLA/LGPD
 * assinado, pagamento em dia, etc) -- não é um módulo vendável do Contrato,
 * é sobre a relação do tenant com a Oravel em si. Intencionalmente sem
 * canAccess() por feature: todo tenant precisa ver isso, incluído no
 * Contrato ou não.
 */
class ComplianceStatus extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Status de Conformidade';

    protected static string $view = 'filament.pages.compliance-status';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 10;

    public function getViewData(): array
    {
        $tenant = auth()->user()->tenant;
        $signature = $tenant?->signature;
        $requiredBy = $tenant?->signature_required_by;

        $checks = [
            [
                'name' => 'Contrato SLA + LGPD Assinado',
                'status' => $signature ? 'completed' : 'pending',
                'date' => $signature?->signed_at,
                'details' => $signature ? "Assinado em {$signature->signed_at->format('d/m/Y')} por {$signature->name}" : "Prazo: {$requiredBy?->format('d/m/Y')}",
                'icon' => $signature ? '✅' : '⏳',
            ],
            [
                'name' => 'Pagamento em Dia',
                'status' => $this->getPaymentStatus($tenant),
                'details' => $this->getPaymentDetails($tenant),
                'icon' => $this->getPaymentIcon($tenant),
            ],
            [
                'name' => 'Documentação Providenciada',
                'status' => $this->getDocumentationStatus($tenant),
                'details' => 'Dados cadastrais e plano confirmados',
                'icon' => $this->getDocumentationIcon($tenant),
            ],
            [
                'name' => 'Acesso à Plataforma',
                'status' => $this->getAccessStatus($tenant, $signature),
                'details' => $this->getAccessDetails($tenant, $signature),
                'icon' => $this->getAccessIcon($tenant, $signature),
            ],
        ];

        return [
            'tenant' => $tenant,
            'signature' => $signature,
            'checks' => $checks,
            'overallStatus' => $this->getOverallStatus($checks),
        ];
    }

    private function getPaymentStatus($tenant)
    {
        $status = $tenant?->asaas_payment_status;
        if ($status === 'em_dia') {
            return 'completed';
        }
        if ($status === 'atrasado') {
            return 'warning';
        }

        return 'pending';
    }

    private function getPaymentDetails($tenant)
    {
        return match ($tenant?->asaas_payment_status) {
            'em_dia' => 'Última cobrança processada com sucesso',
            'atrasado' => 'Atenção: Pagamento atrasado',
            default => 'Aguardando processamento',
        };
    }

    private function getPaymentIcon($tenant)
    {
        return match ($tenant?->asaas_payment_status) {
            'em_dia' => '✅',
            'atrasado' => '⚠️',
            default => '⏳',
        };
    }

    private function getDocumentationStatus($tenant)
    {
        return $tenant && $tenant->cpf_cnpj ? 'completed' : 'pending';
    }

    private function getDocumentationIcon($tenant)
    {
        return $tenant && $tenant->cpf_cnpj ? '✅' : '⏳';
    }

    private function getAccessStatus($tenant, $signature)
    {
        if (! $signature) {
            $requiredBy = $tenant?->signature_required_by;
            if ($requiredBy && now()->isAfter($requiredBy)) {
                return 'warning';
            }

            return 'pending';
        }

        return 'completed';
    }

    private function getAccessDetails($tenant, $signature)
    {
        if ($signature) {
            return 'Plataforma totalmente acessível';
        }
        $requiredBy = $tenant?->signature_required_by;
        if ($requiredBy && now()->isAfter($requiredBy)) {
            return 'Acesso restrito: prazo de assinatura vencido';
        }

        return 'Acesso normal (assinatura pendente)';
    }

    private function getAccessIcon($tenant, $signature)
    {
        if ($signature) {
            return '✅';
        }
        $requiredBy = $tenant?->signature_required_by;
        if ($requiredBy && now()->isAfter($requiredBy)) {
            return '⚠️';
        }

        return '⏳';
    }

    private function getOverallStatus($checks)
    {
        $completed = count(array_filter($checks, fn ($c) => $c['status'] === 'completed'));
        $total = count($checks);
        $percentage = (int) (($completed / $total) * 100);

        if ($percentage === 100) {
            return ['status' => 'completed', 'label' => 'Totalmente Conforme'];
        }
        if ($percentage >= 50) {
            return ['status' => 'warning', 'label' => 'Parcialmente Conforme'];
        }

        return ['status' => 'pending', 'label' => 'Ações Pendentes'];
    }
}
