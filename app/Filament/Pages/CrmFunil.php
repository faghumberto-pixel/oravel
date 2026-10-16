<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AIAnalysisResource;
use App\Models\AIAnalysis;
use App\Models\CrmEtapa;
use App\Models\CrmLead;
use App\Models\CrmMotivoPerda;
use App\Models\CrmPipeline;
use App\Services\CommercialLeadAnalysisService;
use App\Services\CrmFunilPadrao;
use App\Services\CrmFunilService;
use App\Support\Tenancy;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CrmFunil extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $navigationLabel = 'Funil de Vendas';

    protected static ?string $title = 'Funil de Vendas';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.crm-funil';

    public string $search = '';

    /** 'kanban' (padrão, arrastável) ou 'piramide' (contagem por etapa). */
    public string $visao = 'kanban';

    public ?string $funilId = null;

    /** Etapa aberta na lista da visão pirâmide. */
    public ?string $selectedStage = null;

    // Modal de motivo da perda (aberto ao soltar/mover um lead para uma etapa "Perdido").
    public bool $mostrarPerda = false;

    public ?string $perdaLeadId = null;

    public ?string $perdaEtapaId = null;

    public ?string $perdaMotivo = null;

    public ?string $perdaDetalhe = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', CrmLead::class);
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function mount(): void
    {
        if ($tenant = Tenancy::current()) {
            CrmFunilPadrao::garantir((string) $tenant->id);
        }

        $this->funilId = $this->getFunilAtual()?->id;
    }

    /** @return Collection<int, CrmPipeline> */
    public function getFunis(): Collection
    {
        return CrmPipeline::where('ativo', true)->orderByDesc('padrao')->orderBy('nome')->get();
    }

    public function getFunilAtual(): ?CrmPipeline
    {
        $funis = $this->getFunis();

        return $funis->firstWhere('id', $this->funilId) ?? $funis->first();
    }

    /** @return Collection<int, CrmEtapa> */
    public function getEtapas(): Collection
    {
        $funil = $this->getFunilAtual();

        return $funil ? $funil->etapas()->get() : collect();
    }

    public function trocarFunil(string $funilId): void
    {
        $this->funilId = $funilId;
        $this->selectedStage = null;
    }

    public function setVisao(string $visao): void
    {
        $this->visao = $visao === 'piramide' ? 'piramide' : 'kanban';
    }

    public function getMotivosPerda(): array
    {
        return CrmMotivoPerda::opcoes() ?: CrmLead::lostReasonCategoryLabels();
    }

    /**
     * Pirâmide: etapas abertas e ganho em sequência ("Perdido" fica fora, é saída
     * e não degrau). Cada faixa estreita por posição, não pela contagem.
     */
    public function getFunnelStages(): array
    {
        $etapas = $this->getEtapas()->reject(fn (CrmEtapa $e) => $e->isPerdido())->values();
        $total = max($etapas->count(), 1);

        $counts = CrmLead::whereIn('etapa_id', $etapas->pluck('id'))
            ->selectRaw('etapa_id, count(*) as total')->groupBy('etapa_id')->pluck('total', 'etapa_id');

        return $etapas->map(fn (CrmEtapa $e, int $i) => [
            'stage' => $e->id,
            'etapa' => $e,
            'label' => $e->nome,
            'count' => (int) ($counts[$e->id] ?? 0),
            'topWidth' => round(100 * ($total - $i) / $total, 1),
            'bottomWidth' => round(100 * ($total - $i - 1) / $total, 1),
            'url' => '#',
        ])->all();
    }

    public function canUseAI(): bool
    {
        return (bool) Tenancy::current()?->hasFeature('ia_diagnostico_avarias');
    }

    public function selectStage(string $etapaId): void
    {
        $this->selectedStage = $this->selectedStage === $etapaId ? null : $etapaId;
    }

    public function getSelectedStageLeads(): Collection
    {
        if (! $this->selectedStage) {
            return collect();
        }

        return $this->leadsQuery()->where('etapa_id', $this->selectedStage)->get();
    }

    public function getSelectedStageLabel(): ?string
    {
        return $this->selectedStage ? $this->getEtapas()->firstWhere('id', $this->selectedStage)?->nome : null;
    }

    public function getLostCount(): int
    {
        return $this->leadsQuery(false)->whereIn('etapa_id', $this->getEtapas()->filter->isPerdido()->pluck('id'))->count();
    }

    public function getOpenPipelineValue(): float
    {
        return (float) $this->leadsQuery(false)->whereIn('etapa_id', $this->getEtapas()->filter->isAberta()->pluck('id'))->sum('estimated_value');
    }

    /** Σ valor × probabilidade da etapa, só etapas abertas (mesma ideia da projeção ponderada do Central). */
    public function getWeightedPipelineValue(): float
    {
        return $this->getColunas()->filter(fn (array $c) => $c['etapa']->isAberta())->sum('ponderado');
    }

    public function getAverageTicket(): ?float
    {
        $won = $this->leadsQuery(false)->whereIn('etapa_id', $this->getEtapas()->filter->isGanho()->pluck('id'))
            ->whereNotNull('estimated_value')->get();

        return $won->isEmpty() ? null : (float) $won->avg('estimated_value');
    }

    /** Ganhos ÷ todos os leads do funil. */
    public function getConversionRate(array $rows = []): ?float
    {
        $total = $this->leadsQuery(false)->whereIn('etapa_id', $this->getEtapas()->pluck('id'))->count();

        if ($total === 0) {
            return null;
        }

        $ganhos = $this->leadsQuery(false)->whereIn('etapa_id', $this->getEtapas()->filter->isGanho()->pluck('id'))->count();

        return round(($ganhos / $total) * 100, 1);
    }

    /**
     * Largura de cada faixa (%), afunilando por posição (base larga no topo,
     * ponta estreita embaixo), nunca pela contagem de leads.
     */
    public function getFunnelWidths(): array
    {
        $ids = array_column($this->getFunnelStages(), 'stage');
        $total = count($ids);
        $topWidth = 100;
        $bottomWidth = 34;

        if ($total <= 1) {
            return collect($ids)->mapWithKeys(fn ($id) => [$id => $topWidth])->all();
        }

        return collect($ids)->mapWithKeys(function ($id, $index) use ($total, $topWidth, $bottomWidth) {
            $ratio = $index / ($total - 1);

            return [$id => (int) round($topWidth - ($ratio * ($topWidth - $bottomWidth)))];
        })->all();
    }

    /** Leads do tenant (não-admin só vê os atribuídos a ele), com a busca aplicada. */
    private function leadsQuery(bool $comBusca = true)
    {
        $tenant = Tenancy::current();
        $user = auth()->user();

        $query = CrmLead::where('tenant_id', $tenant?->id)->with('assignedUser')->orderByDesc('created_at');

        if ($user && ! $user->canSeeAllCrmLeads()) {
            $query->where('assigned_user_id', $user->id);
        }

        if ($comBusca && trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(fn ($q) => $q->where('name', 'ilike', $term)->orWhere('company_name', 'ilike', $term));
        }

        return $query;
    }

    /** Leads do funil atual agrupados por id da etapa. */
    public function getLeadsByStage(): Collection
    {
        if (! Tenancy::current()) {
            return collect();
        }

        return $this->leadsQuery()->whereIn('etapa_id', $this->getEtapas()->pluck('id'))->get()->groupBy('etapa_id');
    }

    public function getStageValue(Collection $leads): float
    {
        return (float) $leads->sum('estimated_value');
    }

    /**
     * Colunas do Kanban: etapa, leads, contagem, valor total e valor
     * ponderado (valor × probabilidade) de cada uma.
     *
     * @return Collection<int, array{etapa: CrmEtapa, leads: Collection, count: int, total: float, ponderado: float}>
     */
    public function getColunas(): Collection
    {
        $porEtapa = $this->getLeadsByStage();

        return $this->getEtapas()->map(function (CrmEtapa $etapa) use ($porEtapa) {
            $leads = $porEtapa->get($etapa->id, collect());
            $total = $this->getStageValue($leads);

            return [
                'etapa' => $etapa,
                'leads' => $leads,
                'count' => $leads->count(),
                'total' => $total,
                'ponderado' => $etapa->isAberta() ? round($total * $etapa->probabilidade / 100, 2) : ($etapa->isGanho() ? $total : 0.0),
            ];
        });
    }

    /**
     * Chamado ao soltar um card (Sortable) ou pelo seletor de fallback. Soltar
     * numa etapa "Perdido" abre o modal de motivo em vez de mover direto.
     */
    public function moverLead(string $leadId, string $etapaId): void
    {
        $lead = CrmLead::where('tenant_id', Tenancy::current()?->id)->find($leadId);
        $etapa = CrmEtapa::where('tenant_id', Tenancy::current()?->id)->find($etapaId);

        if (! $lead || ! $etapa) {
            return;
        }

        if ($etapa->isPerdido()) {
            $this->perdaLeadId = $lead->id;
            $this->perdaEtapaId = $etapa->id;
            $this->perdaMotivo = null;
            $this->perdaDetalhe = null;
            $this->mostrarPerda = true;

            return;
        }

        $this->aplicarMovimento($lead, $etapa);
    }

    public function confirmarPerda(): void
    {
        $lead = CrmLead::where('tenant_id', Tenancy::current()?->id)->find($this->perdaLeadId);
        $etapa = CrmEtapa::where('tenant_id', Tenancy::current()?->id)->find($this->perdaEtapaId);

        if (! $lead || ! $etapa) {
            $this->cancelarPerda();

            return;
        }

        if ($this->aplicarMovimento($lead, $etapa, $this->perdaMotivo, $this->perdaDetalhe)) {
            $this->cancelarPerda();
        }
    }

    public function cancelarPerda(): void
    {
        $this->reset(['mostrarPerda', 'perdaLeadId', 'perdaEtapaId', 'perdaMotivo', 'perdaDetalhe']);
    }

    private function aplicarMovimento(CrmLead $lead, CrmEtapa $etapa, ?string $motivo = null, ?string $detalhe = null): bool
    {
        try {
            app(CrmFunilService::class)->moverLead($lead, $etapa, auth()->user(), $motivo, $detalhe);
        } catch (ValidationException $e) {
            Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();

            return false;
        } catch (\InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return false;
        }

        Notification::make()->title('Lead movido para '.$etapa->nome.'.')->success()->send();

        return true;
    }

    /**
     * Mesma trava de moveStage(): nao-admin so' converte lead atribuido a
     * ele mesmo. So' cria o Cliente (sem Contract, ver CrmLead::convertToClient()).
     */
    public function enviarParaErp(string $leadId): void
    {
        $tenant = Tenancy::current();
        $user = auth()->user();

        $lead = CrmLead::where('tenant_id', $tenant?->id)->find($leadId);

        if (! $lead || ! $lead->isWon() || $lead->client_id || ! $tenant->hasFeature('tabela_clients')) {
            return;
        }

        if (! $user->canSeeAllCrmLeads() && $lead->assigned_user_id !== $user->id) {
            return;
        }

        $lead->enviarParaErp();

        Notification::make()->title('Cliente criado no ERP a partir da conta.')->success()->send();
    }

    /**
     * Mesma trava de moveStage()/converterEmCliente(): nao-admin so'
     * analisa lead atribuido a ele mesmo.
     */
    public function analisarComIA(string $leadId, CommercialLeadAnalysisService $service): void
    {
        $tenant = Tenancy::current();
        $user = auth()->user();

        $lead = CrmLead::where('tenant_id', $tenant?->id)->find($leadId);

        if (! $lead) {
            return;
        }

        if (! $user->canSeeAllCrmLeads() && $lead->assigned_user_id !== $user->id) {
            return;
        }

        $analysis = $service->analyze($lead, $user->id);

        if ($analysis->status === AIAnalysis::STATUS_CONCLUIDA) {
            Notification::make()
                ->title('Análise concluída')
                ->success()
                ->actions([
                    NotificationAction::make('ver')
                        ->label('Ver análise')
                        ->url(AIAnalysisResource::getUrl('view', ['record' => $analysis]))
                        ->button(),
                ])
                ->send();
        } else {
            Notification::make()
                ->title('Não foi possível concluir a análise')
                ->body($analysis->error)
                ->danger()
                ->send();
        }
    }
}
