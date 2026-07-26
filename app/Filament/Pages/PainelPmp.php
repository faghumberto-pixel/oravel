<?php

namespace App\Filament\Pages;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\MaintenanceOrderResource;
use App\Models\MaintenanceDueAlert;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceStatusHistory;
use App\Support\Tenancy;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Dashboard de Planejamento de Manutenção Preventiva (PMP): Kanban do ciclo
 * de planejamento (A Fazer -> Planejado -> Em Andamento -> Em Revisão ->
 * Concluído) + KPIs/gráficos, tudo sobre dados reais -- não é um painel
 * paralelo:
 *
 * - Coluna "A Fazer" = MaintenanceDueAlert (já existia, alimentado por
 *   maintenance:check-due-alerts) sem nenhuma OS preventiva não-cancelada
 *   pra aquele par Ativo+Plano ainda.
 * - Colunas seguintes = MaintenanceOrder (maintenance_type=Preventiva),
 *   reaproveitando o MESMO internal_status usado pelo Kanban de Oficina
 *   (MaintenanceKanban) -- aguardando_diagnostico/em_manutencao/
 *   teste_qualidade/concluido -- pra não criar um vocabulário de status
 *   paralelo. Arrastar um card muda o dado real (mesma tabela, mesmo
 *   campo), então o Kanban de Oficina reflete a mudança também.
 * - Arrastar "A Fazer" -> "Planejado" CRIA a OS de verdade
 *   (moveAlertToPlanned), preenchendo maintenance_plan_id -- ver migration
 *   2026_07_24_092852.
 */
#[BelongsToFeature('maintenance')]
class PainelPmp extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'PCM';

    protected static ?string $navigationLabel = 'Dashboard PMP';

    protected static ?string $title = 'Planejamento de Manutenção Preventiva';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.painel-pmp';

    public const COL_A_FAZER = 'a_fazer';

    public const COL_PLANEJADO = 'planejado';

    public const COL_EM_ANDAMENTO = 'em_andamento';

    public const COL_EM_REVISAO = 'em_revisao';

    public const COL_CONCLUIDO = 'concluido';

    /**
     * Coluna -> [internal_status, status] gravados na OS quando um card real
     * é solto ali. "a_fazer" não tem OS associada (fica de fora do mapa).
     */
    public const COLUMN_TO_ORDER_STATUS = [
        self::COL_PLANEJADO => ['internal_status' => 'aguardando_diagnostico', 'status' => 'Aberto'],
        self::COL_EM_ANDAMENTO => ['internal_status' => 'em_manutencao', 'status' => 'Em Andamento'],
        self::COL_EM_REVISAO => ['internal_status' => 'teste_qualidade', 'status' => 'Em Andamento'],
        self::COL_CONCLUIDO => ['internal_status' => 'concluido', 'status' => 'Concluída'],
    ];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    /**
     * Grupo de KPI aberto no momento (null = modal fechado). Clicar num
     * card de KPI abre a lista de equipamentos daquele grupo, com
     * localização, pra apoiar a decisão de mandar técnico.
     */
    public ?string $openKpiGroup = null;

    public function openKpiList(string $group): void
    {
        $this->openKpiGroup = $group;
    }

    public function closeKpiList(): void
    {
        $this->openKpiGroup = null;
    }

    public function getKpiGroupLabel(): string
    {
        return match ($this->openKpiGroup) {
            'concluidas' => 'Manutenções Concluídas',
            'em_andamento' => 'Em Andamento',
            'revisao_pendente' => 'Revisão Pendente',
            'criticas' => 'Críticas / Bloqueadas',
            default => 'Ordens de Serviço Totais',
        };
    }

    /**
     * Equipamentos do grupo de KPI aberto, com localização (Asset.endereco,
     * o mesmo campo que alimenta o Mapa de Equipamentos) pra apoiar a
     * decisão de qual técnico mandar pra onde.
     */
    public function getKpiListItems(): Collection
    {
        if (! $this->openKpiGroup) {
            return collect();
        }

        $query = $this->baseOrdersQuery()->with(['asset', 'technician', 'client']);

        $query = match ($this->openKpiGroup) {
            'concluidas' => $query->where('internal_status', 'concluido'),
            'em_andamento' => $query->where('internal_status', 'em_manutencao'),
            'revisao_pendente' => $query->where('internal_status', 'teste_qualidade'),
            'criticas' => $query->where(fn ($q) => $q->whereIn('internal_status', ['aguardando_peca', 'aguardando_peca_canibalizado'])
                ->orWhere('is_prazo_fatal', true)),
            default => $query,
        };

        return $query->latest('scheduled_at')->get()->map(fn (MaintenanceOrder $o) => [
            'os_number' => $o->os_number ?? Str::substr($o->id, 0, 8),
            'asset_name' => $o->asset?->name ?? 'Equipamento indisponível',
            'patrimonio' => $o->asset?->patrimonio ?? '—',
            'location' => $o->asset?->endereco ?: $o->client?->name ?: 'Localização não informada',
            'technician' => $o->technician?->name ?? 'Sem técnico',
            'edit_url' => MaintenanceOrderResource::getUrl('edit', ['record' => $o]),
        ])->values();
    }

    /**
     * Query base das OS preventivas do tenant, nunca cancelada.
     */
    protected function baseOrdersQuery()
    {
        return MaintenanceOrder::where('tenant_id', Tenancy::current()?->id)
            ->where('maintenance_type', MaintenanceOrder::TYPE_PREVENTIVE)
            ->where('status', '!=', 'Cancelada');
    }

    public function getKpis(): array
    {
        $orders = $this->baseOrdersQuery()->get([
            'id', 'internal_status', 'status', 'is_prazo_fatal',
        ]);

        $total = $orders->count();
        $concluidas = $orders->where('internal_status', 'concluido')->count();
        $emAndamento = $orders->where('internal_status', 'em_manutencao')->count();
        $revisaoPendente = $orders->where('internal_status', 'teste_qualidade')->count();
        $criticas = $orders->filter(fn ($o) => in_array($o->internal_status, ['aguardando_peca', 'aguardando_peca_canibalizado'], true)
            || $o->is_prazo_fatal)->count();

        return compact('total', 'concluidas', 'emAndamento', 'revisaoPendente', 'criticas');
    }

    /**
     * Alertas de preventiva vencida (MaintenanceDueAlert) que ainda NÃO tem
     * nenhuma OS preventiva aberta pro mesmo par Ativo+Plano -- são os
     * "cards virtuais" da coluna A Fazer.
     */
    public function getPendingAlerts(): Collection
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return collect();
        }

        $osAtivasPorPar = $this->baseOrdersQuery()
            ->whereNotNull('maintenance_plan_id')
            ->get(['asset_id', 'maintenance_plan_id'])
            ->map(fn ($o) => $o->asset_id.'|'.$o->maintenance_plan_id)
            ->flip();

        return MaintenanceDueAlert::where('tenant_id', $tenant->id)
            ->with(['asset', 'maintenancePlan'])
            ->get()
            ->reject(fn (MaintenanceDueAlert $alert) => isset($osAtivasPorPar[$alert->asset_id.'|'.$alert->maintenance_plan_id]))
            ->filter(fn (MaintenanceDueAlert $alert) => $alert->asset && $alert->maintenancePlan)
            ->sortByDesc('alerted_at')
            ->values();
    }

    /**
     * Monta as 5 colunas prontas pra blade: cada item já vem com id
     * (prefixado "alert:"/"os:" pro drag-and-drop saber o que soltar),
     * código, título, técnico, data e progresso.
     */
    public function getKanbanColumns(): array
    {
        $orders = $this->baseOrdersQuery()->with(['asset', 'technician'])->get();

        // toBase(): map() numa Eloquent Collection continua sendo uma
        // Eloquent Collection mesmo carregando arrays -- merge() dela exige
        // Model::getKey(), quebra com os arrays de osToCard(). toBase()
        // devolve uma Collection generica, onde merge()/concat() funcionam
        // como esperado com arrays.
        $cardsFor = function (string $internalStatus) use ($orders) {
            return $orders->where('internal_status', $internalStatus)
                ->sortByDesc('is_prazo_fatal')
                ->map(fn (MaintenanceOrder $o) => $this->osToCard($o))
                ->values()
                ->toBase();
        };

        return [
            self::COL_A_FAZER => [
                'title' => 'A Fazer (Para Planejar)',
                'tone' => 'critical',
                'cards' => $this->getPendingAlerts()->map(fn (MaintenanceDueAlert $a) => $this->alertToCard($a))->values(),
            ],
            self::COL_PLANEJADO => [
                'title' => 'Planejado',
                'tone' => 'info',
                'cards' => $cardsFor('aguardando_diagnostico'),
            ],
            self::COL_EM_ANDAMENTO => [
                'title' => 'Em Andamento',
                'tone' => 'lightblue',
                'cards' => $cardsFor('em_manutencao')->merge($cardsFor('aguardando_peca'))->merge($cardsFor('aguardando_peca_canibalizado'))->values(),
            ],
            self::COL_EM_REVISAO => [
                'title' => 'Em Revisão',
                'tone' => 'warning',
                'cards' => $cardsFor('teste_qualidade'),
            ],
            self::COL_CONCLUIDO => [
                'title' => 'Concluído',
                'tone' => 'success',
                'cards' => $cardsFor('concluido'),
            ],
        ];
    }

    private function alertToCard(MaintenanceDueAlert $alert): array
    {
        $status = $alert->maintenancePlan->dueStatusForAsset($alert->asset);

        return [
            'id' => 'alert:'.$alert->id,
            'code' => 'PLN-'.strtoupper(Str::substr($alert->id, 0, 5)),
            'title' => $alert->asset->name.' — '.$alert->maintenancePlan->name,
            'patrimonio' => $alert->asset->patrimonio,
            'tech' => 'A definir',
            'date' => $status['due_at_date'] ? Carbon::parse($status['due_at_date'])->format('d/m/y') : '—',
            'progress' => 0,
            'blocked' => false,
            'avatars' => [],
        ];
    }

    private function osToCard(MaintenanceOrder $order): array
    {
        $progress = match ($order->internal_status) {
            'aguardando_diagnostico' => 10,
            'em_manutencao', 'aguardando_peca', 'aguardando_peca_canibalizado' => 55,
            'teste_qualidade' => 85,
            'concluido' => 100,
            default => 5,
        };

        $avatars = [];
        if (in_array($order->internal_status, ['teste_qualidade', 'concluido'], true) && $order->technician) {
            $avatars[] = $this->initials($order->technician->name);
        }

        return [
            'id' => 'os:'.$order->id,
            'code' => $order->os_number ?? Str::substr($order->id, 0, 8),
            'title' => ($order->asset?->name ?? 'Equipamento indisponível').' — '.($order->description ? Str::limit($order->description, 40) : 'Manutenção preventiva'),
            'patrimonio' => $order->asset?->patrimonio,
            'tech' => $order->technician?->name ?? 'A definir',
            'date' => $order->scheduled_at?->format('d/m/y') ?? $order->created_at->format('d/m/y'),
            'progress' => $progress,
            'blocked' => in_array($order->internal_status, ['aguardando_peca', 'aguardando_peca_canibalizado'], true) || (bool) $order->is_prazo_fatal,
            'avatars' => $avatars,
        ];
    }

    private function initials(string $name): string
    {
        $parts = explode(' ', trim($name));

        return Str::upper(Str::substr($parts[0] ?? '', 0, 1).Str::substr($parts[count($parts) - 1] ?? '', 0, 1));
    }

    /**
     * Combina os 3 tipos reais de alerta: preventiva vencida (ainda sem OS),
     * OS com prazo fatal e OS travada aguardando peça -- mesmo critério de
     * "crítico" usado no restante do sistema (PainelCriticidade, badges do
     * Kanban de Oficina), só que juntado numa lista só.
     */
    public function getCriticalAlerts(): Collection
    {
        $vencidas = $this->getPendingAlerts()->take(4)->map(function (MaintenanceDueAlert $alert) {
            $status = $alert->maintenancePlan->dueStatusForAsset($alert->asset);

            return [
                'text' => $alert->asset->name.' — preventiva "'.$alert->maintenancePlan->name.'" vencida',
                'meta' => $status['overdue_days'] > 0 ? $status['overdue_days'].' dia(s) de atraso' : number_format($status['overdue_hours'], 0).'h de atraso',
                'tone' => 'critical',
            ];
        });

        $prazoFatal = $this->baseOrdersQuery()
            ->where('is_prazo_fatal', true)
            ->where('internal_status', '!=', 'concluido')
            ->with('asset')
            ->latest('prazo_fatal_at')
            ->take(3)
            ->get()
            ->map(fn (MaintenanceOrder $o) => [
                'text' => 'OS #'.($o->os_number ?? Str::substr($o->id, 0, 8)).' em prazo fatal — '.($o->asset?->name ?? '—'),
                'meta' => $o->prazo_fatal_at ? 'vence '.$o->prazo_fatal_at->format('d/m/y') : '—',
                'tone' => 'critical',
            ]);

        $semPeca = $this->baseOrdersQuery()
            ->whereIn('internal_status', ['aguardando_peca', 'aguardando_peca_canibalizado'])
            ->with('asset')
            ->take(3)
            ->get()
            ->map(fn (MaintenanceOrder $o) => [
                'text' => 'OS #'.($o->os_number ?? Str::substr($o->id, 0, 8)).' aguardando peça — '.($o->asset?->name ?? '—'),
                'meta' => 'bloqueada',
                'tone' => 'warning',
            ]);

        return $vencidas->concat($prazoFatal)->concat($semPeca)->take(8)->values();
    }

    /**
     * Drag-and-drop real: move um card entre colunas.
     * "alert:{id}" só pode ir pra Planejado (cria a OS de verdade).
     * "os:{id}" atualiza internal_status/status da OS existente.
     */
    public function moveCard(string $cardId, string $targetColumn): void
    {
        [$kind, $id] = explode(':', $cardId, 2) + [null, null];

        if ($kind === 'alert' && $targetColumn === self::COL_PLANEJADO) {
            $this->createOrderFromAlert($id);

            return;
        }

        if ($kind === 'os' && isset(self::COLUMN_TO_ORDER_STATUS[$targetColumn])) {
            $this->updateOrderColumn($id, $targetColumn);

            return;
        }

        Notification::make()->title('Movimento não permitido')->warning()->send();
    }

    private function createOrderFromAlert(string $alertId): void
    {
        $alert = MaintenanceDueAlert::with(['asset', 'maintenancePlan'])->find($alertId);

        if (! $alert || ! $alert->asset || ! $alert->maintenancePlan) {
            Notification::make()->title('Alerta não encontrado')->danger()->send();

            return;
        }

        $status = $alert->maintenancePlan->dueStatusForAsset($alert->asset);

        MaintenanceOrder::create([
            'tenant_id' => $alert->tenant_id,
            'asset_id' => $alert->asset_id,
            'client_id' => $alert->asset->client_id,
            'maintenance_plan_id' => $alert->maintenance_plan_id,
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => 'Aberto',
            'internal_status' => 'aguardando_diagnostico',
            'scheduled_at' => now(),
            'description' => sprintf(
                'Planejada via Dashboard PMP: "%s" vencida (previsto para %sh, horímetro atual %sh).',
                $alert->maintenancePlan->name,
                number_format($status['due_at_hours'], 1),
                number_format((float) $alert->asset->horimetro_atual, 1),
            ),
        ]);

        Notification::make()->title('Ordem de Serviço planejada com sucesso')->success()->send();
    }

    private function updateOrderColumn(string $orderId, string $targetColumn): void
    {
        $order = $this->baseOrdersQuery()->find($orderId);

        if (! $order) {
            Notification::make()->title('Ordem de Serviço não encontrada')->danger()->send();

            return;
        }

        $target = self::COLUMN_TO_ORDER_STATUS[$targetColumn];
        $oldStatus = $order->internal_status;

        $order->update([
            'internal_status' => $target['internal_status'],
            'status' => $target['status'],
        ]);

        MaintenanceStatusHistory::create([
            'maintenance_order_id' => $order->id,
            'old_status' => $oldStatus,
            'new_status' => $target['internal_status'],
            'user_id' => auth()->id(),
            'created_at' => now(),
        ]);

        Notification::make()->title('Status atualizado')->success()->send();
    }
}
