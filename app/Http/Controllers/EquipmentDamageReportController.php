<?php

namespace App\Http\Controllers;

use App\Models\EquipmentDamage;
use App\Support\DocumentoPagina;

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

        return DocumentoPagina::responder('Laudo técnico de avaria', 'pdf.equipment_damage_report', [
            'damage' => $damage,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);
    }
}
