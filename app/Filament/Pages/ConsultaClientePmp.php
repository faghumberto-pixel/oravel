<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MaintenanceOrderResource;
use App\Models\Asset;
use App\Models\Client;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Actions\Action as PageAction;
use Filament\Notifications\Notification;
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

    public ?string $filterGroupId = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    /**
     * Não usa o padrão table-print genérico (Cache::put de ids + model) --
     * as linhas aqui não são um Model, são uma Collection montada em
     * memória (1 linha por Ativo+Plano, com status/OS/projeções
     * calculados). Segue o padrão de AlocacaoTecnicosPmp::forPrint(): a
     * rota de impressão reconstrói a página com os mesmos filtros da URL
     * (query string), reaproveitando getMaintenanceRowsProperty() em vez
     * de duplicar a lógica de junção no controller.
     */
    protected function getHeaderActions(): array
    {
        return [
            PageAction::make('imprimir_consulta')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn () => (bool) $this->clientId)
                ->url(fn () => route('consulta-cliente-pmp.print', [
                    'clientId' => $this->clientId,
                    'filterAssetId' => $this->filterAssetId,
                    'filterGroupId' => $this->filterGroupId,
                    'filterStatus' => $this->filterStatus,
                    'filterTechnicianId' => $this->filterTechnicianId,
                ]))
                ->openUrlInNewTab(),
        ];
    }

    /**
     * Monta a página em memória (sem HTTP) aplicando os mesmos filtros da
     * URL, pra rota de impressão reaproveitar toda a lógica de junção já
     * escrita aqui -- mesmo padrão de AlocacaoTecnicosPmp::forPrint().
     */
    public static function forPrint(array $filters): self
    {
        $page = new self;
        $page->clientId = $filters['clientId'] ?? null;
        $page->filterAssetId = $filters['filterAssetId'] ?? null;
        $page->filterGroupId = $filters['filterGroupId'] ?? null;
        $page->filterStatus = $filters['filterStatus'] ?? null;
        $page->filterTechnicianId = $filters['filterTechnicianId'] ?? null;

        return $page;
    }

    public function getFilterGroupOptionsProperty(): Collection
    {
        return $this->clientAssets()
            ->pluck('checklistGroup')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->pluck('name', 'id');
    }

    /**
     * Se já existe uma OS (não cancelada) pra este Ativo+Plano, retorna o id
     * dela sem criar outra -- evita duplicar quando o gestor clica de novo.
     * Caso contrário, cria uma OS preventiva nova, mesmo padrão de
     * CoberturaPmp::abrirOs().
     */
    public function resolveOrCreateOrder(string $assetId, string $planId): ?string
    {
        $existing = MaintenanceOrder::where('asset_id', $assetId)
            ->where('maintenance_plan_id', $planId)
            ->where('status', '!=', 'Cancelada')
            ->latest('created_at')
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $asset = Asset::find($assetId);
        if (! $asset) {
            return null;
        }

        $order = MaintenanceOrder::create([
            'tenant_id' => $asset->tenant_id,
            'asset_id' => $asset->id,
            'client_id' => $asset->client_id,
            'maintenance_plan_id' => $planId,
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => 'Aberto',
            'internal_status' => 'aguardando_diagnostico',
            'scheduled_at' => now(),
            'description' => 'Planejada via Consulta por Cliente.',
        ]);

        return $order->id;
    }

    public function abrirOuVerOs(string $assetId, string $planId)
    {
        $orderId = $this->resolveOrCreateOrder($assetId, $planId);

        if (! $orderId) {
            return null;
        }

        Notification::make()->title('OS aberta')->success()->send();

        return redirect(MaintenanceOrderResource::getUrl('edit', ['record' => $orderId]));
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
            ->with([
                'contracts' => fn ($q) => $q->where('client_id', $this->clientId)->where('is_active', true),
                'checklistGroup',
            ])
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

        if ($this->filterGroupId) {
            $assets = $assets->where('checklist_group_id', $this->filterGroupId);
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
