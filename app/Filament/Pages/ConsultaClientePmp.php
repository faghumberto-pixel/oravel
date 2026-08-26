<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\Client;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Responde a pergunta real que motivou a área PMP (usuário 2026-08-26):
 * "tenho N máquinas no cliente X, qual manutenção o técnico deve fazer lá
 * este mês e nos próximos?" -- nenhuma tela do sistema filtrava por
 * cliente antes desta página (ver MaintenanceOrderResource, PainelPmp).
 *
 * Client não tem client_id direto em Asset -- o vínculo é via Contract
 * ativo (Asset::contracts()). Reaproveita MaintenancePlan::applicableFor()
 * e dueStatusForAsset()/projectedDueDates() sem alterar a lógica de
 * vencimento, só agrega/categoriza pra exibição.
 */
class ConsultaClientePmp extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'PMP';

    protected static ?string $navigationLabel = 'Consulta por Cliente';

    protected static ?string $title = 'Manutenções por Cliente';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.consulta-cliente-pmp';

    public ?string $clientId = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    public function getClientsProperty(): Collection
    {
        return Client::where('tenant_id', Tenancy::current()?->id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function clientAssets(): Collection
    {
        if (! $this->clientId) {
            return collect();
        }

        return Asset::where('tenant_id', Tenancy::current()?->id)
            ->whereHas('contracts', fn ($q) => $q->where('client_id', $this->clientId)->where('is_active', true))
            ->get();
    }

    /**
     * Uma linha por Ativo+Plano aplicável, com status atual e projeção. Não
     * há Resource/tela própria pra "manutenção do ativo agrupada por
     * cliente" antes desta página -- monta tudo aqui, sem persistir nada
     * novo (dados vêm só de leitura de MaintenancePlan/MaintenanceOrder).
     */
    public function getMaintenanceRowsProperty(): Collection
    {
        $assets = $this->clientAssets();

        if ($assets->isEmpty()) {
            return collect();
        }

        $rows = collect();

        foreach ($assets as $asset) {
            $plans = MaintenancePlan::applicableFor($asset);

            foreach ($plans as $plan) {
                $status = $plan->dueStatusForAsset($asset);
                $order = MaintenanceOrder::where('tenant_id', $asset->tenant_id)
                    ->where('asset_id', $asset->id)
                    ->where('maintenance_plan_id', $plan->id)
                    ->where('status', '!=', 'Cancelada')
                    ->latest('created_at')
                    ->first();

                $rows->push([
                    'asset' => $asset,
                    'plan' => $plan,
                    'status' => $status,
                    'order' => $order,
                    'category' => $this->categorize($status, $order),
                    'projections' => $plan->projectedDueDates($asset, 3),
                ]);
            }
        }

        return $rows;
    }

    /**
     * @return 'atrasada'|'em_andamento'|'concluida'|'programada'|'pendente'
     */
    protected function categorize(array $status, ?MaintenanceOrder $order): string
    {
        if ($status['is_overdue']) {
            return 'atrasada';
        }

        if ($order) {
            if ($order->internal_status === 'concluido' && $order->finished_at?->isCurrentMonth()) {
                return 'concluida';
            }

            if (in_array($order->internal_status, ['aguardando_diagnostico', 'em_manutencao', 'aguardando_peca', 'aguardando_peca_canibalizado', 'teste_qualidade'], true)) {
                return 'em_andamento';
            }

            if ($order->technician_id && $order->scheduled_at) {
                return 'programada';
            }
        }

        return 'pendente';
    }

    public function getRowsByCategoryProperty(): array
    {
        $rows = $this->maintenanceRows;

        return [
            'atrasada' => $rows->where('category', 'atrasada')->values(),
            'programada' => $rows->where('category', 'programada')->values(),
            'em_andamento' => $rows->where('category', 'em_andamento')->values(),
            'pendente' => $rows->where('category', 'pendente')->values(),
            'concluida' => $rows->where('category', 'concluida')->values(),
        ];
    }
}
