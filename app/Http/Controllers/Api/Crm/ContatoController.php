<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmConta;
use App\Models\CrmContaContato;
use App\Models\CrmLead;
use App\Models\CrmLeadContact;
use App\Models\PropostaComercial;
use App\Models\WhatsappConversa;
use App\Support\CrmApiPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ContatoController extends Controller
{
    use RespondeCrmApi;

    /** Acha lead/conta/contato pelo telefone (só dígitos; compara pelos 8 últimos, então tolera +55, DDD e 9º dígito). */
    public function buscar(Request $request): JsonResponse
    {
        $request->validate(['telefone' => ['required', 'string', 'max:40']]);

        $user = $request->user();
        $digitos = preg_replace('/\D/', '', $request->string('telefone')->toString());

        if (strlen($digitos) < 8) {
            return $this->ok([]);
        }

        $final = substr($digitos, -8);
        $igual = fn (Builder $q, string $coluna) => $q->orWhereRaw("right(regexp_replace(coalesce({$coluna}, ''), '\\D', '', 'g'), 8) = ?", [$final]);

        $achados = [];
        $leadsVisiveis = fn () => CrmApiPresenter::leadsVisiveis($user);

        // Leads: telefone/WhatsApp do lead e telefone dos contatos do lead.
        $leads = $leadsVisiveis()->where(function (Builder $q) use ($igual) {
            $igual($q, 'phone');
            $igual($q, 'whatsapp');
            $q->orWhereIn('id', CrmLeadContact::query()->where(fn ($c) => $igual($c, 'phone'))->select('crm_lead_id'));
        })->get();

        foreach ($leads as $lead) {
            $achados[] = CrmApiPresenter::lead($lead);
        }

        if (Gate::forUser($user)->allows('viewAny', CrmConta::class)) {
            $contas = CrmApiPresenter::contasVisiveis($user)->where(function (Builder $q) use ($igual) {
                $igual($q, 'telefone');
                $igual($q, 'whatsapp');
                $q->orWhereIn('id', CrmContaContato::query()->where(fn ($c) => $igual($c, 'phone'))->select('crm_conta_id'));
            })->get();

            foreach ($contas as $conta) {
                $achados[] = CrmApiPresenter::conta($conta);
            }
        }

        // Conversas de WhatsApp da empresa já ligadas a um lead/cliente.
        $conversas = WhatsappConversa::query()->where(fn ($q) => $igual($q, 'telefone'))->get();
        foreach ($conversas as $conversa) {
            if ($conversa->crm_lead_id && ($lead = $leadsVisiveis()->find($conversa->crm_lead_id))) {
                $achados[] = CrmApiPresenter::lead($lead);
            }
            if ($conversa->crm_conta_id && Gate::forUser($user)->allows('viewAny', CrmConta::class) && ($conta = CrmApiPresenter::contasVisiveis($user)->find($conversa->crm_conta_id))) {
                $achados[] = CrmApiPresenter::conta($conta);
            }
        }

        $unicos = collect($achados)->unique(fn ($a) => $a['tipo'].$a['id'])->take(20)->values()->all();

        return $this->ok($unicos);
    }

    /** Leads abertos e propostas em andamento de um lead ou conta. */
    public function oportunidades(Request $request, string $tipo, string $id): JsonResponse
    {
        $user = $request->user();

        if (! in_array($tipo, ['lead', 'conta'], true)) {
            return $this->erro('tipo_invalido', 'Use "lead" ou "conta".', 422);
        }

        if ($tipo === 'lead') {
            $lead = CrmApiPresenter::leadsVisiveis($user)->find($id);

            if (! $lead) {
                return $this->erro('nao_encontrado', 'Lead não encontrado.', 404);
            }

            $leadsQuery = CrmApiPresenter::leadsVisiveis($user)->whereKey($lead->id);
            $propostasQuery = PropostaComercial::query()->where('crm_lead_id', $lead->id);
        } else {
            Gate::forUser($user)->authorize('viewAny', CrmConta::class);

            $conta = CrmApiPresenter::contasVisiveis($user)->find($id);

            if (! $conta) {
                return $this->erro('nao_encontrado', 'Conta não encontrada.', 404);
            }

            $leadsQuery = CrmApiPresenter::leadsVisiveis($user)->where('crm_conta_id', $conta->id);
            // Propostas dos leads desta conta e, se ela foi enviada ao ERP, as do Cliente vinculado.
            $propostasQuery = PropostaComercial::query()->where(fn ($q) => $q
                ->whereIn('crm_lead_id', CrmLead::query()->where('crm_conta_id', $conta->id)->select('id'))
                ->when($conta->client_id, fn ($qq) => $qq->orWhere('client_id', $conta->client_id)));
        }

        $leads = $leadsQuery->get()->filter(fn (CrmLead $l) => $l->isOpen())->map(fn ($l) => CrmApiPresenter::lead($l))->values()->all();

        $propostas = [];
        if (Gate::forUser($user)->allows('viewAny', PropostaComercial::class)) {
            if (! $user->canSeeAllCrmLeads()) {
                $propostasQuery->where('seller_user_id', $user->id);
            }

            $propostas = $propostasQuery->whereIn('status', CrmApiPresenter::statusAbertos())->get()
                ->map(fn ($p) => CrmApiPresenter::proposta($p))->values()->all();
        }

        return $this->ok(['leads_abertos' => $leads, 'propostas_em_andamento' => $propostas]);
    }
}
