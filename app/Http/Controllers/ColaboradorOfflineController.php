<?php

namespace App\Http\Controllers;

use App\Services\Colaborador\FilaOffline;
use App\Services\Colaborador\PacoteOffline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** API do app do colaborador para trabalhar sem internet: pacote de dados (baixa) e fila de envio (sobe). */
class ColaboradorOfflineController extends Controller
{
    private const MAX_ACOES_POR_ENVIO = 100;

    public function pacote(Request $request, PacoteOffline $pacote): JsonResponse
    {
        return response()->json($pacote->montar($request->user()))->header('Cache-Control', 'no-store');
    }

    public function csrf(Request $request): JsonResponse
    {
        // usuario_id: o celular só envia as ações que ELE (esse usuário) criou -- nunca as de quem usou o aparelho antes.
        return response()->json(['token' => csrf_token(), 'usuario_id' => $request->user()->id])->header('Cache-Control', 'no-store');
    }

    /** Recebe ações em JSON ({acoes:[...]}) ou 1 ação com arquivo (multipart: acao=JSON + arquivo). */
    public function fila(Request $request, FilaOffline $fila): JsonResponse
    {
        if ($request->hasFile('arquivo')) {
            $acoes = [json_decode((string) $request->input('acao'), true) ?: []];
        } else {
            $acoes = array_slice((array) $request->input('acoes', []), 0, self::MAX_ACOES_POR_ENVIO);
        }

        $resultados = [];
        foreach ($acoes as $acao) {
            $resultados[] = $fila->processar($request->user(), (array) $acao, $request->file('arquivo'));
        }

        return response()->json(['resultados' => $resultados])->header('Cache-Control', 'no-store');
    }

    /** Casca da execução offline da OS: o conteúdo é montado no celular (public/js/colab-offline.js). */
    public function executarOffline(string $id): View
    {
        return view('colaborador.offline', ['modo' => 'app', 'osId' => $id]);
    }
}
