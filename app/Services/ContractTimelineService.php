<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\MaintenanceOrder;
use Carbon\Carbon;

class ContractTimelineService
{
    public function getTimelineData(Contract $contract): array
    {
        // Dados básicos do contrato
        $startDate = $contract->start_date;
        $endDate = $contract->end_date;
        $now = now();

        // Cálculos
        $daysElapsed = $startDate->diffInDays($now);
        $totalDays = $startDate->diffInDays($endDate);
        $daysRemaining = max(0, $now->diffInDays($endDate));
        $progress = $totalDays > 0 ? round(($daysElapsed / $totalDays) * 100, 1) : 0;

        // Data sugerida para renovação (60 dias antes)
        $renewalSuggestedDate = $endDate->copy()->subDays(60);

        // Manutenções durante o contrato
        $maintenanceOrders = MaintenanceOrder::where('asset_id', $contract->asset_id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->get();

        // Formatar eventos de manutenção
        $maintenanceEvents = $maintenanceOrders->map(function ($mo) {
            return [
                'date' => $mo->created_at->format('d/m/Y'),
                'type' => $mo->type ?? 'manutenção',
                'description' => $mo->reported_problem?->name ?? $mo->description ?? 'Manutenção',
                'color' => $this->getMaintenanceColor($mo->type),
            ];
        })->toArray();

        return [
            'contract' => $contract,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'daysElapsed' => $daysElapsed,
            'daysRemaining' => $daysRemaining,
            'totalDays' => $totalDays,
            'progress' => $progress,
            'renewalSuggestedDate' => $renewalSuggestedDate,
            'maintenanceEvents' => $maintenanceEvents,
            'maintenanceCount' => count($maintenanceEvents),
        ];
    }

    private function getMaintenanceColor(string $type): string
    {
        return match ($type) {
            'preventiva' => '#10b981',
            'corretiva' => '#ef4444',
            'inspeção' => '#f59e0b',
            default => '#6366f1',
        };
    }
}
