<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\View\View;

class AssetPublicController extends Controller
{
    /**
     * Exibe os detalhes públicos do ativo via QR Code.
     */
    public function show(string $uuid): View
    {
        // Busca o ativo pelo UUID. 
        // O firstOrFail() garante que se o QR for inválido, o usuário recebe um 404.
        $asset = Asset::where('id', $uuid)
            ->with(['maintenanceOrders' => function($query) {
                $query->latest()->limit(5); // Carrega as últimas 5 manutenções
            }])
            ->firstOrFail();

        return view('assets.public-view', compact('asset'));
    }
}