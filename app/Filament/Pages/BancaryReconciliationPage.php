<?php

namespace App\Filament\Pages;

use App\Models\AccountReceivable;
use App\Support\Tenancy;
use Carbon\Carbon;
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

    public ?string $dateStart = null;

    public ?string $dateEnd = null;

    public ?string $syncStatus = null;

    public function mount(): void
    {
        $this->dateStart = now()->subDays(30)->format('Y-m-d');
        $this->dateEnd = now()->format('Y-m-d');
    }

    // Achado em simulação real 2026-09-24: o docblock da classe já dizia
    // "restrito a admins ou usuários com permissão de leitura de contas a
    // receber", mas o código nunca implementou isso -- qualquer usuário
    // autenticado via qualquer tenant enxergava a tela, plano incluído ou não.
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', AccountReceivable::class);
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function getViewData(): array
    {
        $tenant = Tenancy::current();

        if (! $tenant) {
            return [
                'automaticallySettled' => collect(),
                'pendingConfirmation' => collect(),
                'manualSettlement' => collect(),
                'summary' => [],
            ];
        }

        $dateStart = $this->dateStart ? Carbon::parse($this->dateStart) : now()->subDays(30);
        $dateEnd = $this->dateEnd ? Carbon::parse($this->dateEnd) : now();

        $automaticallySettled = $this->getAutomaticallySettled($tenant->id, $dateStart, $dateEnd);
        $pendingConfirmation = $this->getPendingConfirmation($tenant->id, $dateStart, $dateEnd);
        $manualSettlement = $this->getManualSettlement($tenant->id, $dateStart, $dateEnd);

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

    private function getAutomaticallySettled(string $tenantId, Carbon $dateStart, Carbon $dateEnd): Collection
    {
        $query = AccountReceivable::where('tenant_id', $tenantId)
            ->whereNotNull('asaas_payment_id')
            ->where('status', 'pago')
            ->whereBetween('payment_date', [$dateStart, $dateEnd]);

        if ($this->syncStatus && $this->syncStatus !== 'automatic') {
            $query->where(false);
        }

        return $query->orderBy('payment_date', 'desc')->get();
    }

    private function getPendingConfirmation(string $tenantId, Carbon $dateStart, Carbon $dateEnd): Collection
    {
        $query = AccountReceivable::where('tenant_id', $tenantId)
            ->whereNotNull('asaas_payment_id')
            ->whereIn('status', ['pendente', 'atrasado'])
            ->whereBetween('due_date', [$dateStart, $dateEnd]);

        if ($this->syncStatus && $this->syncStatus !== 'pending') {
            $query->where(false);
        }

        return $query->orderBy('due_date', 'desc')->get();
    }

    private function getManualSettlement(string $tenantId, Carbon $dateStart, Carbon $dateEnd): Collection
    {
        $query = AccountReceivable::where('tenant_id', $tenantId)
            ->whereNull('asaas_payment_id')
            ->whereBetween('due_date', [$dateStart, $dateEnd]);

        if ($this->syncStatus && $this->syncStatus !== 'manual') {
            $query->where(false);
        }

        return $query->orderBy('due_date', 'desc')->get();
    }
}
