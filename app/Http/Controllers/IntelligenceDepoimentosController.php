<?php

namespace App\Http\Controllers;

use App\Domain\Intelligence\Models\IntelligenceTestimonial;
use Illuminate\Http\JsonResponse;

/** Depoimentos aprovados e autorizados, para a página estática oravel.com.br/intelligence (lista vazia = a seção não aparece). */
class IntelligenceDepoimentosController extends Controller
{
    public function index(): JsonResponse
    {
        $lista = IntelligenceTestimonial::publicaveis()->limit(6)->get(['nome', 'cargo', 'empresa', 'texto'])
            ->map(fn ($t) => ['nome' => $t->nome, 'cargo' => $t->cargo, 'empresa' => $t->empresa, 'texto' => $t->texto])->values();

        // CORS: o grupo api do Laravel já libera leitura entre origens (conteúdo público, sem cookie). Só o cache é definido aqui.
        $resposta = response()->json($lista)->header('Cache-Control', 'public, max-age=300');

        return $resposta;
    }
}
