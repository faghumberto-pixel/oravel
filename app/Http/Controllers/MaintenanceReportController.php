<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Log;

class MaintenanceReportController extends Controller
{
    public function show()
    {
        try {
            $tenant = Tenancy::current();

            if (! $tenant) {
                return view('reports.maintenance-minimal', ['groupedOrders' => collect()]);
            }

            $orders = MaintenanceOrder::where('tenant_id', $tenant->id)
                ->with(['asset', 'statusHistories'])
                ->get();

            if ($orders->isEmpty()) {
                Log::warning("Relatório: Nenhuma ordem de serviço encontrada para o tenant {$tenant->id}.");
            }

            $groupedOrders = $orders->groupBy(fn($o) => $o->internal_status ?: 'Sem Status');

            return view('reports.maintenance-minimal', compact('groupedOrders'));

        } catch (\Exception $e) {
            Log::error("Erro fatal no relatório: " . $e->getMessage());
            return "Erro ao processar relatório. Verifique o log.";
        }
    }
}