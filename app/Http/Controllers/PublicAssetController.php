<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;

class PublicAssetController extends Controller
{
    public function show(string $uuid)
    {
        // Busca o ativo pelo UUID, incluindo relacionamentos
        $asset = Asset::where('id', $uuid)->firstOrFail();

        // Aqui você pode redirecionar para uma rota protegida pelo Filament
        // ou criar uma View Blade simples com as informações.
        return view('assets.public-view', compact('asset'));
    }
}