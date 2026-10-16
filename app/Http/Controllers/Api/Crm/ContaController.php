<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmConta;
use App\Models\CrmProduto;
use App\Support\CrmApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ContaController extends Controller
{
    use RespondeCrmApi;

    /** Ficha da conta (cliente do CRM): dados, contatos e negócios. */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', CrmConta::class);

        $conta = CrmApiPresenter::contasVisiveis($user)->with('contatos')->find($id);

        if (! $conta) {
            return $this->erro('nao_encontrado', 'Conta não encontrada.', 404);
        }

        return $this->ok(CrmApiPresenter::conta($conta) + [
            'segmento' => $conta->segmento,
            'responsavel_id' => $conta->responsavel_user_id,
            'contatos' => $conta->contatos->map(fn ($c) => ['id' => $c->id, 'nome' => $c->name, 'cargo' => $c->role_title, 'telefone' => $c->phone, 'email' => $c->email, 'principal' => (bool) $c->is_primary])->values()->all(),
            'negocios' => CrmApiPresenter::leadsVisiveis($user)->where('crm_conta_id', $conta->id)->get()->map(fn ($l) => CrmApiPresenter::lead($l))->values()->all(),
            'custom' => (object) ($conta->custom ?? []),
        ]);
    }

    /** Catálogo de produtos e serviços ativos (para montar itens de interesse). */
    public function produtos(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', CrmProduto::class);

        return $this->ok(CrmProduto::where('ativo', true)->orderBy('nome')->get()->map(fn ($p) => [
            'id' => $p->id, 'codigo' => $p->codigo, 'nome' => $p->nome, 'tipo' => $p->tipo, 'unidade' => $p->unidade, 'preco' => (float) $p->preco,
        ])->values()->all());
    }
}
