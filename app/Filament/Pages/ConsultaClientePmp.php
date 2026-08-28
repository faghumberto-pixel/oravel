<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\Client;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\User;
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
 *
 * Pedido do usuário 2026-08-28: virar uma planilha única (uma linha por
 * Ativo+Plano, coluna de Status) em vez de 5 tabelas separadas por
 * categoria, com filtros de Equipamento/Status/Técnico.
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

    public ?string $filterAssetId = null;

    public ?string $filterStatus = null;

    public ?string $filterTechnicianId = null;

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
            ->with(['contracts' => fn ($q) => $q->where('client_id', $this->clientId)->where('is_active', true)])
            ->get();
    }

    /**
     * Opções pro select de equipamento -- só os ativos do cliente
     * selecionado, pra não listar o cadastro inteiro do tenant.
     */
    public function getFilterAssetOptionsProperty(): Collection
    {
        return $this->clientAssets()->pluck('name', 'id');
    }

    /**
     * Opções pro select de técnico -- só quem já tem alguma OS vinculada
     * às manutenções deste cliente, pra não listar o quadro inteiro do
     * tenant quando a maioria nunca atendeu esse cliente.
     */
    public function getFilterTechnicianOptionsProperty(): Collection
    {
        $assetIds = $this->clientAssets()->pluck('id');
        if ($assetIds->isEmpty()) {
            return collect();
        }

        $technicianIds = MaintenanceOrder::where('tenant_id', Tenancy::current()?->id)
            ->whereIn('asset_id', $assetIds)
            ->whereNotNull('technician_id')
            ->distinct()
            ->pluck('technician_id');

        return User::whereIn('id', $technicianIds)->orderBy('name')->pluck('name', 'id');
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

        if ($this->filterAssetId) {
            $assets = $assets->where('id', $this->filterAssetId);
        }

        if ($assets->isEmpty()) {
            return collect();
        }

        $rows = collect();

        foreach ($assets as $asset) {
            $plans = MaintenancePlan::applicableFor($asset);
            $location = $asset->contracts->first()?->resolvedLocation();

            foreach ($plans as $plan) {
                $status = $plan->dueStatusForAsset($asset);
                $order = MaintenanceOrder::where('tenant_id', $asset->tenant_id)
                    ->where('asset_id', $asset->id)
                    ->where('maintenance_plan_id', $plan->id)
                    ->where('status', '!=', 'Cancelada')
                    ->with('technician')
                    ->latest('created_at')
                    ->first();

                $lastCompleted = MaintenanceOrder::where('tenant_id', $asset->tenant_id)
                    ->where('asset_id', $asset->id)
                    ->where('maintenance_plan_id', $plan->id)
                    ->whereNotNull('finished_at')
                    ->latest('finished_at')
                    ->first();

                $rows->push([
                    'asset' => $asset,
                    'plan' => $plan,
                    'status' => $status,
                    'order' => $order,
                    'category' => $this->categorize($status, $order),
                    'projections' => $plan->projectedDueDates($asset, 3),
                    'location' => $location,
                    'last_completed_at' => $lastCompleted?->finished_at,
                ]);
            }
        }

        if ($this->filterStatus) {
            $rows = $rows->where('category', $this->filterStatus)->values();
        }

        if ($this->filterTechnicianId) {
            $rows = $rows->filter(fn ($row) => $row['order']?->technician_id === $this->filterTechnicianId)->values();
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
}
