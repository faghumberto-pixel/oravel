<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmLeadInteraction;
use App\Models\CrmMotivoPerda;
use App\Models\CrmOrigem;
use App\Models\CrmPipeline;
use App\Models\User;
use App\Services\CrmFunilPadrao;
use App\Support\CrmCamposCustom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfiguracaoController extends Controller
{
    use RespondeCrmApi;

    /** Listas que a extensão precisa para montar os seletores (funis/etapas, origens, motivos, responsáveis, campos). */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        CrmFunilPadrao::garantir((string) $user->tenant_id);

        return $this->ok([
            'funis' => CrmPipeline::where('ativo', true)->orderByDesc('padrao')->orderBy('nome')->with('etapas')->get()->map(fn ($f) => [
                'id' => $f->id, 'nome' => $f->nome, 'padrao' => $f->padrao,
                'etapas' => $f->etapas->map(fn ($e) => ['id' => $e->id, 'nome' => $e->nome, 'tipo' => $e->tipo, 'probabilidade' => $e->probabilidade])->values()->all(),
            ])->values()->all(),
            'origens' => CrmOrigem::where('ativo', true)->orderBy('ordem')->get()->map(fn ($o) => ['slug' => $o->slug, 'nome' => $o->nome])->values()->all(),
            'motivos_perda' => CrmMotivoPerda::where('ativo', true)->orderBy('ordem')->get()->map(fn ($m) => ['slug' => $m->slug, 'nome' => $m->nome, 'exige_detalhe' => $m->exige_detalhe])->values()->all(),
            'responsaveis' => User::where('tenant_id', $user->tenant_id)->where('is_approved', true)->orderBy('name')->get(['id', 'name', 'email'])
                ->map(fn ($u) => ['id' => $u->id, 'nome' => $u->name, 'email' => $u->email])->values()->all(),
            'campos_personalizados' => CrmCamposCustom::campos('lead')->map(fn ($c) => [
                'chave' => $c->chave, 'nome' => $c->nome, 'tipo' => $c->tipo, 'obrigatorio' => $c->obrigatorio, 'opcoes' => array_values($c->opcoesLabels()),
            ])->values()->all(),
            'canais' => CrmLeadInteraction::channelLabels(),
        ]);
    }
}
