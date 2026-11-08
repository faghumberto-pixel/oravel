<?php

namespace App\Http\Controllers;

use App\Models\CkAnswer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Foto de um item de checklist. Fica em disco privado: so sai por aqui, para quem pode ver a execucao. A resposta e buscada pelo escopo
 * do tenant (outro tenant = 404) e o caminho precisa estar na pasta do proprio tenant.
 */
class CkFotoController extends Controller
{
    public function show(CkAnswer $resposta, int $indice): StreamedResponse
    {
        $execucao = $resposta->execucao;
        abort_unless($execucao, 404);
        Gate::authorize('view', $execucao);

        $caminho = ((array) $resposta->fotos)[$indice] ?? null;
        abort_unless($caminho && str_starts_with($caminho, 'ck-execucoes/'.$resposta->tenant_id.'/') && ! str_contains($caminho, '..') && Storage::disk('local')->exists($caminho), 404);

        return Storage::disk('local')->response($caminho);
    }
}
