<?php

namespace App\Http\Controllers;

use App\Filament\Pages\MaintenanceKanban;
use App\Models\User;
use Illuminate\Http\Request;

class MaintenanceKanbanPrintController extends Controller
{
    public function show(Request $request)
    {
        $mode = $request->query('mode', 'oficina');
        $technicianId = $request->query('technician');
        $hidden = $request->query('hidden') ? explode(',', $request->query('hidden')) : [];
        $search = $request->query('search');

        $visibleStatuses = array_diff_key(MaintenanceKanban::statusMap($mode), array_flip($hidden));

        $orders = MaintenanceKanban::buildQuery($mode, $technicianId, $search)->get();
        $groupedOrders = MaintenanceKanban::groupByStatus($orders, $mode);

        return view('reports.kanban-print', [
            'visibleStatuses' => $visibleStatuses,
            'groupedOrders' => $groupedOrders,
            'technicianName' => $technicianId ? User::find($technicianId)?->name : null,
            'generatedAt' => now(),
        ]);
    }
}
