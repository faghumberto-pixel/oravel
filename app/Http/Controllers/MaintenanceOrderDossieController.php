<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class MaintenanceOrderDossieController extends Controller
{
    /**
     * Gera o PDF do Dossiê da Ordem de Manutenção.
     */
    public function download(MaintenanceOrder $record)
    {
        $order = $record->load(['asset', 'client', 'technician', 'evidences']);

        $pdf = Pdf::loadView('pdf.maintenance_order_dossie', [
            'order' => $order,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download("dossie-os-{$order->os_number}.pdf");
    }
}