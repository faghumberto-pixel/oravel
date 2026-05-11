<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Http\Resources\Api\AssetResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssetController extends Controller
{
    /**
     * Listagem protegida pelo TenantScope e formatada pelo Resource.
     */
    public function index(): AnonymousResourceCollection
    {
        // Retornamos a coleção usando o tradutor da API
        return AssetResource::collection(Asset::all());
    }

    /**
     * Criação com injeção automática de Checklist Padrão e Tenant ID.
     */
    public function store(Request $request): AssetResource
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'asset_category'    => 'required|string',
            'patrimonio'        => 'required|string|unique:assets',
            'criticality_level' => 'required|string',
            'status'            => 'required|string',
            'description'       => 'nullable|string',
            'serial_number'     => 'nullable|string',
            'checklist'         => 'nullable|array',
        ]);

        // Automação: Se o checklist estiver vazio, busca o padrão da categoria
        if (empty($validated['checklist'])) {
            $validated['checklist'] = Asset::getDefaultChecklist($validated['asset_category']);
        }

        $asset = Asset::create($validated);

        return new AssetResource($asset);
    }

    /**
     * Exibição de um único ativo carregando o histórico de logs.
     */
    public function show($id): AssetResource
    {
        // Carregamos o relacionamento 'logs' para o Resource exibir o histórico
        $asset = Asset::with('logs')->findOrFail($id);
        
        return new AssetResource($asset);
    }

    /**
     * Atualização com validação e retorno formatado.
     */
    public function update(Request $request, $id): AssetResource
    {
        $asset = Asset::findOrFail($id);
        
        $validated = $request->validate([
            'name'              => 'sometimes|required|string|max:255',
            'description'       => 'nullable|string',
            'status'            => 'sometimes|required|string',
            'criticality_level' => 'sometimes|required|string',
            'checklist'         => 'sometimes|required|array',
        ]);

        $asset->update($validated);
        
        return new AssetResource($asset);
    }

    /**
     * Deleção (respeita SoftDeletes se configurado).
     */
    public function destroy($id)
    {
        Asset::findOrFail($id)->delete();
        
        return response()->json(null, 204);
    }

    /**
     * Endpoint extra para o Front-end consultar o checklist padrão antes de criar.
     */
    public function getDefaultChecklist(string $category)
    {
        return response()->json(Asset::getDefaultChecklist($category));
    }
}
