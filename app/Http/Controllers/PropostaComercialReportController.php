<?php

namespace App\Http\Controllers;

use App\Models\PropostaComercial;
use App\Support\DocumentoPagina;

class PropostaComercialReportController extends Controller
{
    public function download(PropostaComercial $record)
    {
        $proposta = $record->load(['client', 'sellerUser', 'items']);

        return DocumentoPagina::responder('Proposta comercial', 'pdf.proposta-comercial', [
            'proposta' => $proposta,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);
    }

    public function print(PropostaComercial $record)
    {
        $proposta = $record->load(['client', 'sellerUser', 'items']);

        return view('proposta-comercial.print', ['proposta' => $proposta]);
    }
}
