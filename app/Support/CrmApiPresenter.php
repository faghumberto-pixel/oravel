<?php

namespace App\Support;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\CrmLeadResource;
use App\Filament\Resources\PropostaComercialResource;
use App\Models\Client;
use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\PropostaComercial;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Monta os arrays JSON da API do CRM: só campos de negócio, nunca tenant_id, tokens ou dados internos. */
class CrmApiPresenter
{
    /** Leads que o usuário pode ver (não-admin só os atribuídos a ele). O escopo de tenant vem do BelongsToTenant. */
    public static function leadsVisiveis(User $user): Builder
    {
        $query = CrmLead::query();

        if (! $user->canSeeAllCrmLeads()) {
            $query->where('assigned_user_id', $user->id);
        }

        return $query;
    }

    public static function lead(CrmLead $lead, bool $completo = false): array
    {
        $lead->loadMissing('etapa', 'assignedUser');

        $dados = [
            'tipo' => 'lead',
            'id' => $lead->id,
            'nome' => $lead->company_name ?: $lead->name,
            'contato' => $lead->name,
            'empresa' => $lead->company_name,
            'documento' => $lead->document,
            'telefone' => $lead->phone,
            'whatsapp' => $lead->whatsapp,
            'email' => $lead->email,
            'etapa' => $lead->etapa ? ['id' => $lead->etapa->id, 'nome' => $lead->etapa->nome, 'tipo' => $lead->etapa->tipo] : null,
            'valor' => $lead->estimated_value !== null ? (float) $lead->estimated_value : null,
            'previsao_fechamento' => $lead->previsao_fechamento?->toDateString(),
            'proximo_contato' => $lead->next_followup_date?->toDateString(),
            'responsavel' => $lead->assignedUser ? ['id' => $lead->assignedUser->id, 'nome' => $lead->assignedUser->name] : null,
            'url' => CrmLeadResource::getUrl('edit', ['record' => $lead->id], panel: 'admin'),
        ];

        if (! $completo) {
            return $dados;
        }

        return $dados + [
            'origem' => $lead->source,
            'cidade' => $lead->city,
            'uf' => $lead->uf,
            'custom' => (object) ($lead->custom ?? []),
            'contatos' => $lead->contacts->map(fn ($c) => ['id' => $c->id, 'nome' => $c->name, 'cargo' => $c->role_title, 'telefone' => $c->phone, 'email' => $c->email, 'principal' => (bool) $c->is_primary])->values()->all(),
            'interacoes' => $lead->interactions()->limit(10)->get()->map(fn ($i) => self::interacao($i))->values()->all(),
            'propostas_em_andamento' => PropostaComercial::query()
                ->where('crm_lead_id', $lead->id)->whereIn('status', self::statusAbertos())
                ->get()->map(fn ($p) => self::proposta($p))->values()->all(),
        ];
    }

    public static function cliente(Client $cliente): array
    {
        return [
            'tipo' => 'cliente',
            'id' => $cliente->id,
            'nome' => $cliente->name,
            'documento' => $cliente->document ?: $cliente->cpf_cnpj,
            'telefone' => $cliente->phone,
            'whatsapp' => $cliente->whatsapp,
            'email' => $cliente->email,
            'url' => ClientResource::getUrl('edit', ['record' => $cliente->id], panel: 'admin'),
        ];
    }

    public static function interacao(CrmLeadInteraction $i): array
    {
        return [
            'id' => $i->id,
            'canal' => $i->channel,
            'direcao' => $i->direcao,
            'resumo' => $i->summary,
            'transcricao' => $i->transcricao,
            'proximo_passo' => $i->next_action,
            'proximo_contato' => $i->next_followup_date?->toDateString(),
            'etapa_na_epoca' => $i->stage_at_time,
            'tem_audio' => $i->hasMedia('audio'),
            'registrado_em' => $i->contact_date?->toIso8601String(),
        ];
    }

    public static function proposta(PropostaComercial $p): array
    {
        return [
            'id' => $p->id,
            'status' => $p->status,
            'status_rotulo' => PropostaComercial::statusLabels()[$p->status] ?? $p->status,
            'valor' => (float) $p->total_value,
            'url' => PropostaComercialResource::getUrl('edit', ['record' => $p->id], panel: 'admin'),
        ];
    }

    /** Propostas "em andamento": ainda sem resposta final do cliente. */
    public static function statusAbertos(): array
    {
        return [PropostaComercial::STATUS_RASCUNHO, PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL, PropostaComercial::STATUS_APROVADA_INTERNA];
    }
}
