<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    // Listagem protegida pelo TenantScope
    public function index()
    {
        return response()->json(Asset::all());
    }

    // Criação protegida pela Trait BelongsToTenant (injetará o tenant_id automaticamente)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'patrimonio'  => 'required|string|unique:assets',
            'status'      => 'required|string',
        ]);

        return response()->json(Asset::create($validated), 201);
    }

    // Exibição de um único ativo (o find já respeita o scope)
    public function show($id)
    {
        return response()->json(Asset::findOrFail($id));
    }

    // Atualização
    public function update(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);
        
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'sometimes|required|string',
        ]);

        $asset->update($validated);
        return response()->json($asset);
    }

    // Deleção
    public function destroy($id)
    {
        Asset::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}