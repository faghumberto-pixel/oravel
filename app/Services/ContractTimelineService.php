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
            ->with('reportedProblem')
            ->orderBy('created_at')
            ->get();

        // Formatar eventos de manutenção
        $maintenanceEvents = $maintenanceOrders->map(function ($mo) {
            $type = $mo->type ?? 'manutenção';
            return [
                'date' => $mo->created_at->format('d/m/Y'),
                'type' => $type,
                'description' => $mo->reportedProblem?->name ?? $mo->description ?? 'Manutenção',
                'color' => $this->getMaintenanceColor($type),
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

    private function getMaintenanceColor(?string $type): string
    {
        $type = strtolower(trim($type ?? 'manutenção'));
        return match ($type) {
            'preventiva' => '#10b981',
            'corretiva' => '#ef4444',
            'inspeção' => '#f59e0b',
            'preventive' => '#10b981',
            'corrective' => '#ef4444',
            'check-out' => '#3b82f6',
            'check-in' => '#8b5cf6',
            default => '#6366f1',
        };
    }
}
