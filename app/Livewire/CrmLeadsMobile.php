<?php

namespace App\Livewire;

use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Leads no celular (campo do vendedor): minha carteira, retornos de hoje, e "registrar contato" com próximo passo em poucos
 * toques. Usa o mesmo registro de interação do CRM (CrmLeadInteraction); o observer dele atualiza último contato e retorno do lead.
 * Vendedor sem permissão de ver todos os leads só enxerga os dele (mesma regra de CrmLeadResource).
 */
#[Layout('layouts.checklist-mobile')]
class CrmLeadsMobile extends Component
{
    public string $busca = '';

    public string $filtro = 'todos';   // 'todos' | 'retornos'

    public ?string $leadId = null;

    public string $canal = CrmLeadInteraction::CHANNEL_TELEFONE;

    public string $resumo = '';

    public ?string $proximoPasso = null;

    public ?string $proximoRetorno = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', CrmLead::class);
    }

    private function base()
    {
        $user = auth()->user();

        return CrmLead::query()->when(! $user->canSeeAllCrmLeads(), fn ($q) => $q->where('assigned_user_id', $user->id));
    }

    public function getLeadsProperty()
    {
        return $this->base()
            ->when(trim($this->busca) !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', '%'.trim($this->busca).'%')
                ->orWhere('company_name', 'ilike', '%'.trim($this->busca).'%')
                ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $this->busca).'%')))
            ->when($this->filtro === 'retornos', fn ($q) => $q->whereDate('next_followup_date', '<=', now()->toDateString()))
            ->orderByRaw('next_followup_date asc nulls last')->orderByDesc('updated_at')
            ->limit(60)->get();
    }

    public function getRetornosProperty(): int
    {
        return $this->base()->whereDate('next_followup_date', '<=', now()->toDateString())->count();
    }

    public function getLeadProperty(): ?CrmLead
    {
        return $this->leadId ? $this->base()->with(['interactions' => fn ($q) => $q->limit(5)])->find($this->leadId) : null;
    }

    public function abrir(string $id): void
    {
        $this->leadId = $this->base()->whereKey($id)->value('id');
        $this->reset('resumo', 'proximoPasso', 'proximoRetorno');
        $this->canal = CrmLeadInteraction::CHANNEL_TELEFONE;
    }

    public function fechar(): void
    {
        $this->leadId = null;
    }

    public function registrar(): void
    {
        $lead = $this->lead;
        abort_unless($lead, 404);
        Gate::authorize('update', $lead);

        $dados = $this->validate([
            'canal' => ['required', 'in:'.implode(',', [CrmLeadInteraction::CHANNEL_TELEFONE, CrmLeadInteraction::CHANNEL_WHATSAPP, CrmLeadInteraction::CHANNEL_EMAIL, CrmLeadInteraction::CHANNEL_PRESENCIAL])],
            'resumo' => ['required', 'string', 'min:3', 'max:2000'],
            'proximoPasso' => ['nullable', 'string', 'max:255'],
            'proximoRetorno' => ['nullable', 'date', 'after_or_equal:today'],
        ], [
            'resumo.required' => 'Conte em poucas palavras como foi o contato.',
            'proximoRetorno.after_or_equal' => 'O retorno precisa ser hoje ou depois.',
        ]);

        CrmLeadInteraction::create([
            'tenant_id' => $lead->tenant_id,
            'crm_lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'channel' => $dados['canal'],
            'contact_date' => now(),
            'summary' => $dados['resumo'],
            'next_action' => $dados['proximoPasso'] ?: null,
            'next_followup_date' => $dados['proximoRetorno'] ?: null,
            'stage_at_time' => $lead->stage,
        ]);

        $this->leadId = null;
        $this->reset('resumo', 'proximoPasso', 'proximoRetorno');
        session()->flash('ok', 'Contato registrado.');
    }

    public function render()
    {
        return view('livewire.crm-leads-mobile');
    }
}
