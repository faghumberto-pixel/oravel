<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

/**
 * Download de contrato pelo Portal do Cliente. Não usa route-model-binding
 * implícito (evita resolução automática sem filtro) -- resolve
 * manualmente com tenant_id+client_id do guard 'client', mesmo princípio
 * de segurança de toda a Fase 1. 404 (não 403) se não bater, pra não
 * vazar se o ID existe.
 */
class ContractPdfController extends Controller
{
    public function download(string $contract)
    {
        /** @var Client|null $client */
        $client = Auth::guard('client')->user();
        abort_unless($client, 403);

        $record = Contract::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id)
            ->where('id', $contract)
            ->firstOrFail();

        $record->load(['client', 'asset']);

        $pdf = Pdf::loadView('pdf.contract', [
            'contract' => $record,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download("contrato-{$record->contract_number}.pdf");
    }
}
