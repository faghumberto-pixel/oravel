<?php

namespace App\Http\Controllers;

use App\Models\PropostaComercial;
use Barryvdh\DomPDF\Facade\Pdf;

class PropostaComercialReportController extends Controller
{
    public function download(PropostaComercial $record)
    {
        $proposta = $record->load(['client', 'sellerUser', 'items']);

        $pdf = Pdf::loadView('pdf.proposta-comercial', [
            'proposta' => $proposta,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download("proposta-comercial-{$proposta->id}.pdf");
    }

    public function print(PropostaComercial $record)
    {
        $proposta = $record->load(['client', 'sellerUser', 'items']);

        return view('proposta-comercial.print', ['proposta' => $proposta]);
    }
}
