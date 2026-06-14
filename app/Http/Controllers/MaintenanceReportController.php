<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceOrder;
use Illuminate\Support\Facades\Log;

class MaintenanceReportController extends Controller
{
    /**
     * @param string|int $tenant O ID ou Slug vindo da rota
     */
    public function show($tenant)
    {
        try {
            // 1. Busca global para garantir visibilidade dos dados
            // Removemos a filtragem restritiva por tenant momentaneamente para testar se os dados existem
            $orders = MaintenanceOrder::with(['asset', 'statusHistory'])
                ->get();

            // 2. Log para depuração caso a página venha vazia
            if ($orders->isEmpty()) {
                Log::warning("Relatório: Nenhuma ordem de serviço encontrada no banco de dados.");
            }

            // 3. Agrupamento robusto
            $groupedOrders = $orders->groupBy(fn($o) => $o->internal_status ?: 'Sem Status');

            return view('reports.maintenance-minimal', compact('groupedOrders'));

        } catch (\Exception $e) {
            Log::error("Erro fatal no relatório: " . $e->getMessage());
            return "Erro ao processar relatório. Verifique o log.";
        }
    }
}