<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AssetController extends Controller
{
    /**
     * Lista os ativos com filtros opcionais
     */
    public function index(Request ): JsonResponse
    {
        $query = Asset::query();

        // Exemplo de filtro para itens com problema no checklist
        if ($request->has('with_issues')) {
            $query->withChecklistIssues();
        }

        return response()->json($query->get());
    }

    /**
     * Salva o ativo injetando o checklist padrão se estiver vazio
     */
    public function store(Request ): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'required|string',
            'asset_category'    => 'required|string',
            'patrimonio'        => 'nullable|string',
            'criticality_level' => 'required|string',
            'status'            => 'required|string',
            'checklist'         => 'nullable|array',
            'description'       => 'nullable|string',
            'serial_number'     => 'nullable|string',
        ]);

        // Se o checklist não for enviado, gera o padrão baseado na categoria
        if (empty($data['checklist'])) {
            $data['checklist'] = Asset::getDefaultChecklist($data['asset_category']);
        }

        $asset = Asset::create($data);

        return response()->json($asset, 201);
    }

    /**
     * Endpoint para o Front-end consultar o checklist padrão antes de salvar
     */
    public function getDefaultChecklist(string $category): JsonResponse
    {
        return response()->json(Asset::getDefaultChecklist($category));
    }
}
