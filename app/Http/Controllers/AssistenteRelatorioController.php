<?php

namespace App\Http\Controllers;

use App\Models\AssistenteRelatorio;
use App\Models\Tenant;
use Illuminate\Http\Response;

/**
 * Pagina publica (sem login) do relatorio compartilhavel do Assistente. O acesso e'
 * pelo token longo e aleatorio do link + validade (AssistenteRelatorio::VALIDADE_DIAS).
 */
class AssistenteRelatorioController extends Controller
{
    public function show(string $token): Response
    {
        $relatorio = AssistenteRelatorio::porToken($token);

        $headers = [
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ];

        if (! $relatorio) {
            return response()->view('assistente.relatorio-expirado', [], 404)->withHeaders($headers);
        }

        $cliente = $relatorio->tenant_id ? Tenant::find($relatorio->tenant_id)?->name : null;

        return response()->view('assistente.relatorio', [
            'r' => $relatorio,
            'cliente' => $cliente,
        ])->withHeaders($headers);
    }
}
