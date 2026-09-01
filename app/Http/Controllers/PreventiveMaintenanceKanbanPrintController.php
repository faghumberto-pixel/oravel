<?php

namespace App\Http\Controllers;

use App\Filament\Pages\PreventiveMaintenanceKanban;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PreventiveMaintenanceKanbanPrintController extends Controller
{
    public function show(Request $request): View
    {
        $tenant = Tenancy::current();
        if (!$tenant) {
            abort(403);
        }

        $page = new PreventiveMaintenanceKanban();
        $page->startDate = $request->query('startDate');
        $page->endDate = $request->query('endDate');
        $page->technicianId = $request->query('technicianId');
        $page->assetId = $request->query('assetId');
        $page->groupId = $request->query('groupId');
        $page->clientId = $request->query('clientId');

        $records = $page->getRecords();
        $statuses = $page->getStatuses();

        return view('print.preventive-maintenance-kanban', [
            'records' => $records,
            'statuses' => $statuses,
        ]);
    }
}
