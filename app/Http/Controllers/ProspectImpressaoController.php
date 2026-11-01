<?php

namespace App\Http\Controllers;

use App\Models\Prospect;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ProspectImpressaoController extends Controller
{
    public const LIMITE = 1500;

    public function show(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $filtros = (array) $request->query('filtros', []);
        $query = Prospect::query()->filtrar($filtros)->busca($request->query('busca'));
        $total = (clone $query)->count();
        $prospects = $query->orderByDesc('nota')->orderBy('nome')->limit(self::LIMITE)->get();

        $partes = [];
        foreach (Prospect::filtrosAtivos($filtros) as $k => $v) {
            $partes[] = match ($k) {
                'status' => 'Situação: '.(Prospect::STATUS[$v] ?? $v),
                'origem' => 'Lista: '.$v,
                'empresa_nova' => 'Empresas novas',
                'regiao_campinas' => 'Região de Campinas',
                'nota_alta' => 'Nota 8 ou mais',
                'retorno_vencido' => 'Retorno vencido',
                'ano_de' => 'Aberta desde '.$v,
                'ano_ate' => 'Aberta até '.$v,
                default => ucfirst($k).': '.$v,
            };
        }
        if (filled($request->query('busca'))) {
            $partes[] = 'Busca: "'.$request->query('busca').'"';
        }
        $descricao = implode('; ', $partes);

        $pdf = Pdf::loadView('pdf.prospects', compact('prospects', 'total', 'descricao'))->setPaper('a4', 'landscape');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="prospects-'.now()->format('Ymd-His').'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
