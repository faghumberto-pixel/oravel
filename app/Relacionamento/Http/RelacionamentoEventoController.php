<?php

namespace App\Relacionamento\Http;

use App\Http\Controllers\Controller;
use App\Relacionamento\Models\RelInteracao;
use App\Relacionamento\Services\RegistraInteracao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * POST /api/relacionamento/eventos -- o oravel.org (ferramentas, Gazeta, downloads) manda aqui, servidor
 * a servidor, cada acao de uma pessoa identificada. Mesmo molde do /api/inbound/leads: token por canal
 * (X-Channel-Token), falha fechado, resposta unica 401 para "sem token / token errado / canal desligado".
 */
class RelacionamentoEventoController extends Controller
{
    public function store(Request $request, RegistraInteracao $registra): JsonResponse
    {
        $canal = $this->canal((string) $request->header('X-Channel-Token'));
        if ($canal === null) {
            return response()->json(['success' => false, 'message' => 'Não autorizado.'], 401);
        }

        $v = Validator::make($request->all(), [
            'evento_id' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9_\-:.]+$/'],
            'tipo' => ['required', Rule::in(array_keys(RelInteracao::TIPOS))],
            'email' => ['nullable', 'email', 'max:200', 'required_without:telefone'],
            'telefone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 ()+\-.]{8,30}$/', 'required_without:email'],
            'nome' => ['nullable', 'string', 'max:160'],
            'empresa' => ['nullable', 'string', 'max:200'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'max:10'],
            'conteudo_slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9\-\/_]+$/'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'resultado' => ['nullable', 'string', 'max:255'],
            'duracao_segundos' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'visitor_token' => ['nullable', 'string', 'max:64'],
            'utm_source' => ['nullable', 'string', 'max:200'],
            'utm_medium' => ['nullable', 'string', 'max:200'],
            'utm_campaign' => ['nullable', 'string', 'max:200'],
            'ocorrido_em' => ['nullable', 'date', 'before_or_equal:+5 minutes'],
            'consentimento' => ['nullable', 'boolean'],
            'dados' => ['nullable', 'array'],
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        }

        $d = $v->validated();
        $d['origem'] = $canal;
        $d['dedupe_key'] = isset($d['evento_id']) ? "{$canal}:{$d['evento_id']}" : null;
        $d['consentimento_origem'] = ! empty($d['consentimento']) ? $canal : null;
        unset($d['evento_id'], $d['consentimento']);

        $interacao = $registra->registrar($d);

        return response()->json(['success' => true, 'id' => $interacao->id], $interacao->wasRecentlyCreated ? 201 : 200);
    }

    private function canal(string $token): ?string
    {
        if ($token === '') {
            return null;
        }

        $achado = null;
        foreach ((array) config('services.relacionamento.channels', []) as $nome => $canal) {
            $esperado = (string) ($canal['token'] ?? '');
            if ($esperado !== '' && hash_equals($esperado, $token)) {
                $achado = (string) $nome;
            }
        }

        return $achado;
    }
}
