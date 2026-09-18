<?php

namespace App\Filament\Pages;

use App\Models\AccountReceivable;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Collection;

/**
 * Dashboard de Conciliação Bancária: mostra o status de sincronismo de cada
 * AccountReceivable com a Asaas.
 *
 * Categorias:
 * - Com asaas_payment_id + status pago: "Baixado Automaticamente"
 * - Com asaas_payment_id + status pendente/atrasado: "Pendente de Confirmação"
 * - Sem asaas_payment_id: "Cobrança Manual" (requer baixa manual)
 *
 * Acesso restrito a admins ou usuários com permissão de leitura de contas
 * a receber.
 */
class BancaryReconciliationPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static string $view = 'filament.pages.bancary-reconciliation';

    protected static ?string $slug = 'conciliacao-bancaria';

    protected static ?string $title = 'Conciliação Bancária';

    protected static ?string $navigationLabel = 'Conciliação Bancária';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        return $user->isAdmin() || $user->can('ler_contas_receber');
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function getViewData(): array
    {
        $tenant = Tenancy::current();

        if (!$tenant) {
            return [
                'automaticallySettled' => collect(),
                'pendingConfirmation' => collect(),
                'manualSettlement' => collect(),
                'summary' => [],
            ];
        }

        $automaticallySettled = $this->getAutomaticallySettled($tenant->id);
        $pendingConfirmation = $this->getPendingConfirmation($tenant->id);
        $manualSettlement = $this->getManualSettlement($tenant->id);

        $summary = [
            'automaticallySettled' => count($automaticallySettled),
            'automaticallySettledAmount' => $automaticallySettled->sum('amount'),
            'pendingConfirmation' => count($pendingConfirmation),
            'pendingConfirmationAmount' => $pendingConfirmation->sum('amount'),
            'manualSettlement' => count($manualSettlement),
            'manualSettlementAmount' => $manualSettlement->sum('amount'),
        ];

        return [
            'automaticallySettled' => $automaticallySettled,
            'pendingConfirmation' => $pendingConfirmation,
            'manualSettlement' => $manualSettlement,
            'summary' => $summary,
        ];
    }

    private function getAutomaticallySettled(string $tenantId): Collection
    {
        return AccountReceivable::where('tenant_id', $tenantId)
            ->whereNotNull('asaas_payment_id')
            ->where('status', 'pago')
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    private function getPendingConfirmation(string $tenantId): Collection
    {
        return AccountReceivable::where('tenant_id', $tenantId)
            ->whereNotNull('asaas_payment_id')
            ->whereIn('status', ['pendente', 'atrasado'])
            ->orderBy('due_date', 'desc')
            ->get();
    }

    private function getManualSettlement(string $tenantId): Collection
    {
        return AccountReceivable::where('tenant_id', $tenantId)
            ->whereNull('asaas_payment_id')
            ->orderBy('due_date', 'desc')
            ->get();
    }
}
