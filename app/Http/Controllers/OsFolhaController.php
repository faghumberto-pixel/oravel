<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceOrder;
use Illuminate\Support\Facades\Gate;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/** Folha de papel da O.S. (A4): dados da empresa ja impressos e quadros em branco para o que o mecanico faz. */
class OsFolhaController extends Controller
{
    public function imprimir(MaintenanceOrder $maintenanceOrder)
    {
        Gate::authorize('view', $maintenanceOrder);
        $os = $maintenanceOrder->load(['asset.client', 'client', 'technician', 'reportedProblem']);

        $equipamento = $os->asset ? trim($os->asset->name.' '.collect([$os->asset->patrimonio, $os->asset->placa, $os->asset->tag])->filter()->map(fn ($v) => '· '.$v)->take(2)->implode(' ')) : '—';
        $problema = collect([$os->reportedProblem?->description, $os->description])->filter()->unique()->implode(' — ');

        return view('print.os-folha', [
            'os' => $os,
            'cliente' => $os->client?->name ?? $os->asset?->client?->name ?? '—',
            'equipamento' => $equipamento,
            'horimetroAnterior' => $os->asset?->last_horimetro ?? $os->horimetro_anterior,
            'tipo' => $os->maintenance_type,
            'problema' => mb_substr((string) $problema, 0, 500),
            'tecnico' => $os->technician?->name,
            'qr' => (string) QrCode::format('svg')->size(110)->margin(0)->errorCorrection('M')->generate(route('maintenance-orders.field-wizard', $os)),
        ]);
    }
}
