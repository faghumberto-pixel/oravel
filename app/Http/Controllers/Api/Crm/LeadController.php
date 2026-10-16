<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CrmEtapa;
use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\CrmOrigem;
use App\Models\CrmPipeline;
use App\Models\User;
use App\Rules\CpfCnpj;
use App\Services\CnpjLookupService;
use App\Services\CrmFunilPadrao;
use App\Services\CrmFunilService;
use App\Support\Cnpj;
use App\Support\CrmApiPresenter;
use App\Support\CrmCnpjPreenchimento;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    use RespondeCrmApi;

    public function show(Request $request, string $id): JsonResponse
    {
        $lead = CrmApiPresenter::leadsVisiveis($request->user())->find($id);

        if (! $lead) {
            return $this->erro('nao_encontrado', 'Lead não encontrado.', 404);
        }

        return $this->ok(CrmApiPresenter::lead($lead, completo: true));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('create', CrmLead::class);
        CrmFunilPadrao::garantir((string) $user->tenant_id);

        $d = $request->validate([
            'nome' => ['required_without:empresa', 'nullable', 'string', 'max:255'],
            'empresa' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'documento' => ['nullable', 'string', 'max:30', new CpfCnpj],
            'email' => ['nullable', 'email', 'max:255'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'responsavel_id' => ['nullable', 'uuid'],
            'funil_id' => ['nullable', 'uuid'],
            'etapa_id' => ['nullable', 'uuid'],
            'origem' => ['nullable', 'string', 'max:80'],
            'previsao_fechamento' => ['nullable', 'date'],
            'proximo_contato' => ['nullable', 'date', 'after_or_equal:today'],
            'observacao' => ['nullable', 'string', 'max:5000'],
            'custom' => ['nullable', 'array'],
            'preencher_cnpj' => ['nullable', 'boolean'],
            'forcar' => ['nullable', 'boolean'],
        ]);

        if (! ($d['forcar'] ?? false) && ($duplicado = $this->duplicado($user, $d))) {
            return $duplicado;
        }

        $etapa = $this->etapaInicial($d);

        $campos = [
            'name' => $d['nome'] ?? $d['empresa'],
            'company_name' => $d['empresa'] ?? null,
            'phone' => $d['telefone'] ?? null,
            'whatsapp' => $d['whatsapp'] ?? ($d['telefone'] ?? null),
            'document' => $d['documento'] ?? null,
            'email' => $d['email'] ?? null,
        ];

        if (($d['preencher_cnpj'] ?? false) && Cnpj::valido($campos['document'])) {
            if ($dadosCnpj = app(CnpjLookupService::class)->lookup($campos['document'])) {
                $mapa = CrmCnpjPreenchimento::mapear('lead', $dadosCnpj);
                foreach (['company_name', 'address', 'city', 'uf', 'cep', 'phone', 'email'] as $campo) {
                    if (blank($campos[$campo] ?? null) && filled($mapa[$campo] ?? null)) {
                        $campos[$campo] = $mapa[$campo];
                    }
                }
            }
        }

        $lead = CrmLead::create($campos + array_filter([
            'tenant_id' => $user->tenant_id,
            'estimated_value' => $d['valor'] ?? null,
            'previsao_fechamento' => $d['previsao_fechamento'] ?? null,
            'source' => $this->origem($d['origem'] ?? null),
            'assigned_user_id' => $this->responsavel($user, $d['responsavel_id'] ?? null),
            'etapa_id' => $etapa?->id,
            'custom' => $d['custom'] ?? null,
        ], fn ($v) => $v !== null));

        if (! empty($d['proximo_contato']) || ! empty($d['observacao'])) {
            CrmLeadInteraction::create([
                'tenant_id' => $user->tenant_id,
                'crm_lead_id' => $lead->id,
                'user_id' => $user->id,
                'channel' => CrmLeadInteraction::CHANNEL_WHATSAPP,
                'contact_date' => now(),
                'summary' => $d['observacao'] ?? 'Lead criado pela API do CRM.',
                'next_followup_date' => $d['proximo_contato'] ?? null,
                'stage_at_time' => $lead->stage,
            ]);
            $lead->refresh();
        }

        return $this->ok(CrmApiPresenter::lead($lead->refresh(), completo: true), 201);
    }

    public function etapa(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $lead = CrmApiPresenter::leadsVisiveis($user)->find($id);

        if (! $lead) {
            return $this->erro('nao_encontrado', 'Lead não encontrado.', 404);
        }

        Gate::forUser($user)->authorize('update', $lead);

        $d = $request->validate([
            'etapa_id' => ['required', 'uuid'],
            'motivo' => ['nullable', 'string', 'max:80'],
            'detalhe' => ['nullable', 'string', 'max:2000'],
        ]);

        $etapa = CrmEtapa::find($d['etapa_id']);

        if (! $etapa) {
            throw ValidationException::withMessages(['etapa_id' => 'Etapa não encontrada.']);
        }

        try {
            app(CrmFunilService::class)->moverLead($lead, $etapa, $user, $d['motivo'] ?? null, $d['detalhe'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return $this->erro('movimento_negado', $e->getMessage(), 403);
        }

        return $this->ok(CrmApiPresenter::lead($lead->refresh()));
    }

    // ------------------------------------------------------------------ helpers

    /** 409 quando já existe lead (mesmo documento ou telefone) ou cliente (mesmo documento). */
    private function duplicado(User $user, array $d): ?JsonResponse
    {
        $doc = Cnpj::limpar($d['documento'] ?? null);
        $final = strlen($tel = preg_replace('/\D/', '', ($d['telefone'] ?? '').($d['whatsapp'] ?? ''))) >= 8 ? substr($tel, -8) : null;

        if ($doc !== '' && Gate::forUser($user)->allows('viewAny', Client::class)) {
            $cliente = Client::query()
                ->whereRaw("regexp_replace(coalesce(document, ''), '\\D', '', 'g') = ?", [$doc])
                ->orWhereRaw("regexp_replace(coalesce(cpf_cnpj, ''), '\\D', '', 'g') = ?", [$doc])->first();

            if ($cliente) {
                return $this->erro('ja_e_cliente', 'Já existe um cliente com este CNPJ/CPF.', 409, ['existente' => CrmApiPresenter::cliente($cliente)]);
            }
        }

        $lead = CrmLead::query()->where(function ($q) use ($doc, $final) {
            if ($doc !== '') {
                $q->orWhereRaw("regexp_replace(coalesce(document, ''), '\\D', '', 'g') = ?", [$doc]);
            }
            if ($final) {
                $q->orWhereRaw("right(regexp_replace(coalesce(phone, ''), '\\D', '', 'g'), 8) = ?", [$final])
                    ->orWhereRaw("right(regexp_replace(coalesce(whatsapp, ''), '\\D', '', 'g'), 8) = ?", [$final]);
            }
        })->first();

        if (! $lead || ($doc === '' && ! $final)) {
            return null;
        }

        // Não-admin não enxerga lead de colega: avisa que existe, sem vazar os dados dele.
        $visivel = CrmApiPresenter::leadsVisiveis($user)->whereKey($lead->id)->exists();

        return $this->erro('duplicado', 'Já existe um lead com este documento ou telefone.', 409,
            ['existente' => $visivel ? CrmApiPresenter::lead($lead) : null]);
    }

    private function etapaInicial(array $d): ?CrmEtapa
    {
        $etapa = null;

        if (! empty($d['etapa_id'])) {
            $etapa = CrmEtapa::find($d['etapa_id']);

            if (! $etapa || (! empty($d['funil_id']) && $etapa->crm_funil_id !== $d['funil_id'])) {
                throw ValidationException::withMessages(['etapa_id' => 'Etapa não encontrada neste funil.']);
            }
        } elseif (! empty($d['funil_id'])) {
            $funil = CrmPipeline::where('ativo', true)->find($d['funil_id']);

            if (! $funil) {
                throw ValidationException::withMessages(['funil_id' => 'Funil não encontrado.']);
            }

            $etapa = $funil->etapas()->where('tipo', CrmEtapa::TIPO_ABERTA)->first();
        }

        if ($etapa && ! $etapa->isAberta()) {
            throw ValidationException::withMessages(['etapa_id' => 'O lead deve entrar numa etapa em andamento.']);
        }

        return $etapa;
    }

    private function origem(?string $informada): string
    {
        if (blank($informada)) {
            return 'outro';
        }

        $alvo = mb_strtolower(trim($informada));

        return CrmOrigem::all()->first(fn ($o) => $o->slug === $alvo || mb_strtolower($o->nome) === $alvo)?->slug ?? 'outro';
    }

    /** Quem não vê todos os leads sempre fica com o próprio lead; admin pode escolher outro usuário do tenant. */
    private function responsavel(User $user, ?string $escolhido): string
    {
        if (! $escolhido || ! $user->canSeeAllCrmLeads()) {
            return $user->id;
        }

        $existe = User::query()->where('tenant_id', Tenancy::current()?->id ?? $user->tenant_id)->whereKey($escolhido)->exists();

        if (! $existe) {
            throw ValidationException::withMessages(['responsavel_id' => 'Responsável não encontrado na empresa.']);
        }

        return $escolhido;
    }
}
