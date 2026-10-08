<?php

namespace App\Http\Controllers;

use App\Models\AccountReceivable;
use App\Models\Client;
use App\Support\DocumentoPagina;
use Illuminate\Support\Facades\Auth;

/**
 * "Espelho de medição" (PDF) do Portal do Cliente -- mesmo princípio de
 * segurança de ContractPdfController: resolve manualmente com
 * tenant_id+client_id do guard 'client', 404 (não 403) se não bater.
 */
class ClientReceivableMirrorController extends Controller
{
    public function download(string $accountReceivable)
    {
        /** @var Client|null $client */
        $client = Auth::guard('client')->user();
        abort_unless($client, 403);

        $record = AccountReceivable::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id)
            ->where('id', $accountReceivable)
            ->firstOrFail();

        $record->load(['contract.asset', 'billCategory']);

        return DocumentoPagina::responder('Espelho de medição', 'pdf.receivable-mirror', [
            'receivable' => $record,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);
    }
}
