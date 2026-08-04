<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;

/**
 * Pré-carregamento leve pra tela offline de horímetro (resources/js/hour-meter-offline.js):
 * lista de equipamentos + último horímetro conhecido, guardada em localStorage
 * no device pra a busca funcionar sem rede. Separado do payload maior de
 * TechnicianTasksController::tasksOfDay() (que só traz ativos ligados a
 * tarefas do dia) porque aqui o técnico pode apontar horímetro de
 * qualquer equipamento do tenant que tenha acesso, não só o da OS do dia.
 */
class HourMeterPreloadController extends Controller
{
    public function index(): JsonResponse
    {
        $assets = Asset::query()
            ->with('client:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'patrimonio', 'tag', 'horimetro_atual', 'client_id'])
            ->map(fn (Asset $asset) => [
                'id' => $asset->id,
                'name' => $asset->name,
                'patrimonio' => $asset->patrimonio,
                'tag' => $asset->tag,
                'last_reading' => $asset->horimetro_atual,
                'client_name' => $asset->client?->name,
            ]);

        return response()->json([
            'assets' => $assets,
            'preloaded_at' => now()->toIso8601String(),
        ]);
    }
}
