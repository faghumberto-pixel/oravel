<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceOrder;
use App\Support\DocumentoPagina;
use Illuminate\Http\Request;

class MaintenanceOrderDossieController extends Controller
{
    /**
     * Gera o PDF do Dossiê da Ordem de Manutenção.
     */
    public function download(MaintenanceOrder $record)
    {
        $order = $record->load(['asset', 'client', 'technician', 'evidences']);

        return DocumentoPagina::responder('Dossiê da ordem de serviço', 'pdf.maintenance_order_dossie', [
            'order' => $order,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);
    }
}