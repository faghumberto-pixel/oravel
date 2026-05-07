<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ReworkTechnicianChart extends ChartWidget
{
    protected static ?string $heading = 'Ranking de Retrabalho por Técnico';
    protected static ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $data = MaintenanceOrder::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_rework', true)
            ->select('technician_id', DB::raw('count(*) as total'))
            ->groupBy('technician_id')->with('technician')->get();

        return [
            'datasets' => [['label' => 'OS de Retrabalho', 'data' => $data->pluck('total')->toArray(), 'backgroundColor' => '#ec4899']],
            'labels' => $data->pluck('technician.name')->toArray(),
        ];
    }
    protected function getType(): string { return 'bar'; }
}
