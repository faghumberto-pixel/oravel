<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class AssetReportController extends Controller
{
    public function generate(Asset $asset)
    {
        // Carrega os dados necessários para o relatório
        $asset->load(['maintenanceOrders' => function($query) {
            $query->where('status', 'Concluída')->latest();
        }]);

        // Estrutura os dados para a View
        $data = [
            'asset' => $asset,
            'total_maintenance' => $asset->total_maintenance_cost,
            'roi' => $asset->maintenance_roi,
            'status_financeiro' => $asset->lcc_analysis,
            'date' => now()->format('d/m/Y')
        ];

        // Gera o PDF baseado em uma View que criaremos
        $pdf = Pdf::loadView('reports.asset_summary', $data);

        return $pdf->download('Relatorio_Ativo_' . $asset->tag . '.pdf');
    }
}