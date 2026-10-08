<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Support\DocumentoPagina;

class AssetDossierPdfController extends Controller
{
    /**
     * Gera o PDF do Dossiê Rápido do Ativo (mesmo conteúdo sintetizado da
     * tela, pra impressão).
     */
    public function download(Asset $asset)
    {
        $asset->load([
            'client',
            'checklistGroup',
            'abcMatrix',
            'contracts' => fn ($query) => $query->where('status', 'Ativo')->latest('start_date'),
            'contracts.client',
            'damages' => fn ($query) => $query->latest('created_at')->limit(5),
            'maintenanceOrders' => fn ($query) => $query->whereNotIn('status', ['Concluída', 'Cancelada', 'Cancelado'])->latest('created_at'),
            'maintenanceOrders.technician',
            'maintenanceOrders.reportedProblem',
        ]);

        return DocumentoPagina::responder('Dossiê do ativo', 'pdf.asset_dossier', [
            'asset' => $asset,
            'currentContract' => $asset->contracts->first(),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);
    }
}
