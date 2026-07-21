<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;

class QuoteReportController extends Controller
{
    /**
     * Gera o PDF do orçamento -- mesmo padrão de EquipmentDamageReportController.
     */
    public function download(Quote $record)
    {
        $quote = $record->load([
            'client',
            'assignedUser',
            'thirdPartySupplier',
            'items',
            'quotable',
        ]);

        $pdf = Pdf::loadView('pdf.quote', [
            'quote' => $quote,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download("orcamento-{$quote->id}.pdf");
    }
}
