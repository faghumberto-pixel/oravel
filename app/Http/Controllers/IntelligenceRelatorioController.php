<?php

namespace App\Http\Controllers;

use App\Domain\Intelligence\Models\IntelligenceSnapshot;
use App\Domain\Intelligence\Services\EvolucaoService;
use App\Domain\Intelligence\Services\RelatorioDoPainel;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Botões do Dashboard Executivo: gera (ou reaproveita) o relatório compartilhável da fotografia atual e leva o usuário
 * à página mínima de impressão ("ver") ou direto ao WhatsApp ("zap"). Só usuário logado com acesso ao módulo.
 */
class IntelligenceRelatorioController extends Controller
{
    public function __invoke(Request $request, string $acao): RedirectResponse
    {
        abort_unless(in_array($acao, ['ver', 'zap'], true), 404);
        abort_unless(Gate::allows('viewAny', IntelligenceSnapshot::class), 403);

        $tenant = Tenancy::current();
        abort_unless($tenant, 403);

        $resumo = app(EvolucaoService::class)->resumo($tenant->id);
        abort_if($resumo === null, 404, 'Ainda não há fotografia da empresa para compartilhar.');

        $tipo = in_array($request->query('tipo'), ['painel', 'diagnostico', 'plano'], true) ? $request->query('tipo') : 'painel';
        $relatorio = app(RelatorioDoPainel::class)->criar($resumo, (string) Auth::id(), $tipo);

        if ($acao === 'ver') {
            return redirect()->to($relatorio->url());
        }

        return redirect()->away('https://wa.me/?text='.rawurlencode("Relatório Oravel — {$relatorio->titulo}\n{$relatorio->url()}"));
    }
}
