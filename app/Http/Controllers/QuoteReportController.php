<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Support\DocumentoPagina;

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

        return DocumentoPagina::responder('Orçamento', 'pdf.quote', [
            'quote' => $quote,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);
    }
}
