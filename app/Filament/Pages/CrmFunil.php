<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AIAnalysisResource;
use App\Models\AIAnalysis;
use App\Models\CrmLead;
use App\Services\CommercialLeadAnalysisService;
use App\Support\Tenancy;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Collection;

class CrmFunil extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $navigationLabel = 'Funil de Vendas';

    protected static ?string $title = 'Funil de Vendas';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static string $view = 'filament.pages.crm-funil';

    public string $search = '';

    public ?string $selectedStage = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', CrmLead::class);
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function getStages(): array
    {
        return CrmLead::stageLabels();
    }

    /**
     * Estagios que formam o corpo da piramide (fluxo de progressao).
     * "Perdido" fica fora -- e uma saida, nao um degrau do funil.
     */
    public function getFunnelStages(): array
    {
        $stages = $this->getStages();
        unset($stages[CrmLead::STAGE_PERDIDO]);

        return $stages;
    }

    public function canUseAI(): bool
    {
        return (bool) Tenancy::current()?->hasFeature('ia_diagnostico_avarias');
    }

    public function selectStage(string $stageId): void
    {
        $this->selectedStage = $this->selectedStage === $stageId ? null : $stageId;
    }

    /**
     * Largura de cada faixa (%), afunilando por posicao no funil (base larga
     * no topo, ponta estreita embaixo) -- nao pela quantidade de leads.
     * Contagem de leads por estagio raramente decresce estagio a estagio
     * num CRM real (lead pode entrar direto em "qualificado", por exemplo),
     * entao largura proporcional a contagem virava um bloco quadrado
     * sempre que os numeros nao caiam em ordem. O numero real de leads e
     * valor continuam exibidos como texto em cada faixa, so a largura que
     * e' fixa por posicao.
     */
    public function getFunnelWidths(): array
    {
        $stages = array_keys($this->getFunnelStages());
        $total = count($stages);

        $topWidth = 100;
        $bottomWidth = 34;

        if ($total <= 1) {
            return collect($stages)->mapWithKeys(fn ($stage) => [$stage => $topWidth])->all();
        }

        return collect($stages)->mapWithKeys(function ($stage, $index) use ($total, $topWidth, $bottomWidth) {
            $ratio = $index / ($total - 1);
            $width = $topWidth - ($ratio * ($topWidth - $bottomWidth));

            return [$stage => (int) round($width)];
        })->all();
    }

    /**
     * Leads do tenant agrupados por estagio. Nao-admin ve so os leads
     * atribuidos a ele mesmo -- mesmo criterio usado no CrmAgendaWidget.
     */
    public function getLeadsByStage(): Collection
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return collect();
        }

        $user = auth()->user();

        $query = CrmLead::where('tenant_id', $tenant->id)
            ->with('assignedUser');

        if (! $user->canSeeAllCrmLeads()) {
            $query->where('assigned_user_id', $user->id);
        }

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', $term)
                    ->orWhere('company_name', 'ilike', $term);
            });
        }

        $leads = $query->orderByDesc('created_at')->get();

        return $leads->groupBy(fn (CrmLead $lead) => array_key_exists($lead->stage, $this->getStages()) ? $lead->stage : CrmLead::STAGE_NOVO);
    }

    public function getStageValue(Collection $leads): float
    {
        return (float) $leads->sum('estimated_value');
    }

    /**
     * Chamado pelo select de estagio na lista do estagio selecionado.
     * Nao-admin so pode mover leads atribuidos a ele mesmo. Mover para
     * "perdido" exige o motivo (coletado via prompt() no JS antes de
     * chamar isso).
     */
    public function moveStage(string $leadId, string $newStage, ?string $lostReason = null): void
    {
        if (! array_key_exists($newStage, $this->getStages())) {
            return;
        }

        $tenant = Tenancy::current();
        $user = auth()->user();

        $lead = CrmLead::where('tenant_id', $tenant?->id)->find($leadId);

        if (! $lead) {
            return;
        }

        if (! $user->canSeeAllCrmLeads() && $lead->assigned_user_id !== $user->id) {
            return;
        }

        if ($newStage === CrmLead::STAGE_PERDIDO && trim((string) $lostReason) === '') {
            Notification::make()->title('Informe o motivo da perda.')->danger()->send();

            return;
        }

        $lead->update([
            'stage' => $newStage,
            'lost_reason' => $newStage === CrmLead::STAGE_PERDIDO ? $lostReason : $lead->lost_reason,
        ]);

        Notification::make()->title('Lead movido para '.$this->getStages()[$newStage].'.')->success()->send();
    }

    /**
     * Mesma trava de moveStage(): nao-admin so' converte lead atribuido a
     * ele mesmo. So' cria o Cliente (sem Contract, ver CrmLead::convertToClient()).
     */
    public function converterEmCliente(string $leadId): void
    {
        $tenant = Tenancy::current();
        $user = auth()->user();

        $lead = CrmLead::where('tenant_id', $tenant?->id)->find($leadId);

        if (! $lead || $lead->stage !== CrmLead::STAGE_CONVERTIDO || $lead->client_id) {
            return;
        }

        if (! $user->canSeeAllCrmLeads() && $lead->assigned_user_id !== $user->id) {
            return;
        }

        $lead->convertToClient();

        Notification::make()->title('Cliente criado a partir do Lead.')->success()->send();
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
