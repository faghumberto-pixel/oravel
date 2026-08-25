<?php

namespace App\Http\Controllers;

use App\Models\EquipmentDamage;
use Barryvdh\DomPDF\Facade\Pdf;

class EquipmentDamageReportController extends Controller
{
    /**
     * Gera o PDF do Laudo Jurídico de Avaria de Equipamento.
     */
    public function download(EquipmentDamage $record)
    {
        $damage = $record->load([
            'maintenanceOrder',
            'asset',
            'reportedBy',
            'supervisorReviewedBy',
            'commercialReviewedBy',
            'replacementAsset',
            'followUps.user',
            'media',
            'quotes',
        ]);

        $pdf = Pdf::loadView('pdf.equipment_damage_report', [
            'damage' => $damage,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download("laudo-avaria-{$damage->maintenanceOrder->os_number}.pdf");
    }
}
