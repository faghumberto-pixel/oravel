<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AssetController extends Controller
{
    // Isolamento por tenant vem de Asset::class usar Concerns\BelongsToTenant
    // (global scope); autorização por plano/permissão (AssetPolicy ->
    // AbstractPolicy) é explícita abaixo -- Gate::authorize não é chamado
    // automaticamente por rota de API, diferente do painel Filament.
    public function index()
    {
        Gate::authorize('viewAny', Asset::class);

        return response()->json(Asset::all());
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Asset::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'patrimonio' => 'required|string|unique:assets',
            'status' => 'required|string',
        ]);

        return response()->json(Asset::create($validated), 201);
    }

    public function show($id)
    {
        $asset = Asset::findOrFail($id);

        Gate::authorize('view', $asset);

        return response()->json($asset);
    }

    public function update(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);

        Gate::authorize('update', $asset);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|string',
        ]);

        $asset->update($validated);

        return response()->json($asset);
    }

    public function destroy($id)
    {
        $asset = Asset::findOrFail($id);

        Gate::authorize('delete', $asset);

        $asset->delete();

        return response()->json(null, 204);
    }

    /**
     * Endpoint extra para o Front-end consultar o checklist padrão antes de criar.
     */
    public function getDefaultChecklist(string $category)
    {
        Gate::authorize('viewAny', Asset::class);

        return response()->json(Asset::getDefaultChecklist($category));
    }
}
