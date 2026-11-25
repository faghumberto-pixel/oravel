<?php

namespace App\Http\Controllers;

use App\Domain\Intelligence\Models\IntelligenceTestimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Depoimentos aprovados e autorizados, para a página estática oravel.com.br/intelligence (lista vazia = a seção não aparece). */
class IntelligenceDepoimentosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $lista = IntelligenceTestimonial::publicaveis()->limit(6)->get(['nome', 'cargo', 'empresa', 'texto'])
            ->map(fn ($t) => ['nome' => $t->nome, 'cargo' => $t->cargo, 'empresa' => $t->empresa, 'texto' => $t->texto])->values();

        $resposta = response()->json($lista)->header('Cache-Control', 'public, max-age=300')->header('Vary', 'Origin');
        $origem = (string) $request->headers->get('Origin');
        if (in_array($origem, (array) config('intelligence_site.origens_permitidas'), true)) {
            $resposta->header('Access-Control-Allow-Origin', $origem);
        }

        return $resposta;
    }
}
