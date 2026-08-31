<?php

namespace App\Http\Controllers;

use App\Models\PropostaComercial;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Impressão em lote (via navegador, Ctrl+P) -- réplica do padrão já usado
 * por TablePrintController, aqui como rota dedicada porque a query
 * aceita tanto filtros (status/data/vendedor) quanto uma lista fechada de
 * ids (bulk action da tabela).
 */
class PropostaComercialBatchPrintController extends Controller
{
    public function show(Request $request): View
    {
        $query = PropostaComercial::where('tenant_id', Tenancy::current()?->id)
            ->with(['client', 'sellerUser', 'items']);

        if ($ids = $request->query('ids')) {
            $query->whereIn('id', (array) $ids);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($vendedorId = $request->query('vendedor_id')) {
            $query->where('seller_user_id', $vendedorId);
        }

        if ($dataDe = $request->query('data_de')) {
            $query->whereDate('created_at', '>=', $dataDe);
        }

        if ($dataAte = $request->query('data_ate')) {
            $query->whereDate('created_at', '<=', $dataAte);
        }

        $propostas = $query->orderByDesc('created_at')->get();

        return view('proposta-comercial.print-batch', ['propostas' => $propostas]);
    }
}
