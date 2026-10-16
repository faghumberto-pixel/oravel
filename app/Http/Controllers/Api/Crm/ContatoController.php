<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientContact;
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

    /** Acha lead/cliente/contato pelo telefone (só dígitos; compara pelos 8 últimos, então tolera +55, DDD e 9º dígito). */
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

        if (Gate::forUser($user)->allows('viewAny', Client::class)) {
            $clientes = Client::query()->where(function (Builder $q) use ($igual) {
                $igual($q, 'phone');
                $igual($q, 'whatsapp');
                $q->orWhereIn('id', ClientContact::query()->where(fn ($c) => $igual($c, 'phone'))->select('client_id'));
            })->get();

            foreach ($clientes as $cliente) {
                $achados[] = CrmApiPresenter::cliente($cliente);
            }
        }

        // Conversas de WhatsApp da empresa já ligadas a um lead/cliente.
        $conversas = WhatsappConversa::query()->where(fn ($q) => $igual($q, 'telefone'))->get();
        foreach ($conversas as $conversa) {
            if ($conversa->crm_lead_id && ($lead = $leadsVisiveis()->find($conversa->crm_lead_id))) {
                $achados[] = CrmApiPresenter::lead($lead);
            }
            if ($conversa->client_id && Gate::forUser($user)->allows('viewAny', Client::class) && ($cliente = Client::find($conversa->client_id))) {
                $achados[] = CrmApiPresenter::cliente($cliente);
            }
        }

        $unicos = collect($achados)->unique(fn ($a) => $a['tipo'].$a['id'])->take(20)->values()->all();

        return $this->ok($unicos);
    }

    /** Leads abertos e propostas em andamento de um lead ou cliente. */
    public function oportunidades(Request $request, string $tipo, string $id): JsonResponse
    {
        $user = $request->user();

        if (! in_array($tipo, ['lead', 'cliente'], true)) {
            return $this->erro('tipo_invalido', 'Use "lead" ou "cliente".', 422);
        }

        if ($tipo === 'lead') {
            $lead = CrmApiPresenter::leadsVisiveis($user)->find($id);

            if (! $lead) {
                return $this->erro('nao_encontrado', 'Lead não encontrado.', 404);
            }

            $leadsQuery = CrmApiPresenter::leadsVisiveis($user)->whereKey($lead->id);
            $propostasQuery = PropostaComercial::query()->where('crm_lead_id', $lead->id);
        } else {
            Gate::forUser($user)->authorize('viewAny', Client::class);

            if (! Client::find($id)) {
                return $this->erro('nao_encontrado', 'Cliente não encontrado.', 404);
            }

            $leadsQuery = CrmApiPresenter::leadsVisiveis($user)->where('client_id', $id);
            $propostasQuery = PropostaComercial::query()->where('client_id', $id);
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
