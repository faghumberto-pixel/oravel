<?php

namespace App\Http\Controllers;

use App\Models\FrotaSaidaVeiculo;
use App\Services\Frota\KmRodadoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Impressão do km rodado da frota no cabeçalho/rodapé padrão dos relatórios. */
class KmRodadoPrintController extends Controller
{
    public function show(Request $request, KmRodadoService $servico): View
    {
        abort_unless(Gate::allows('viewAny', FrotaSaidaVeiculo::class), 403);

        [$de, $ate, $rotulo] = KmRodadoService::periodo((string) $request->query('periodo', 'mes'));

        return view('reports.km-rodado', [
            'periodo' => $rotulo, 'veiculos' => $servico->frota($de, $ate), 'percursos' => $servico->porPercurso($de, $ate),
        ]);
    }
}
