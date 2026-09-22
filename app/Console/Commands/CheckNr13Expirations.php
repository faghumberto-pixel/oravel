<?php

namespace App\Console\Commands;

use App\Models\Nr13Document;
use App\Models\Nr13Inspection;
use App\Models\User;
use App\Notifications\Nr13DocumentExpiringNotification;
use App\Notifications\Nr13InspectionExpiringNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Mesmo padrão de App\Console\Commands\CheckEmployeeCertificationExpirations: 30/15/7 dias +
 * vencido/vencida (com janela de 3 dias pra não renotificar pra sempre um documento/inspeção
 * vencido há meses), pros dois tipos de vencimento do módulo NR-13.
 */
class CheckNr13Expirations extends Command
{
    protected $signature = 'nr13:check-expirations';

    protected $description = 'Notifica documentos e inspeções NR-13 vencendo (30/15/7 dias) ou vencidos.';

    public function handle(): int
    {
        $total = 0;
        $total += $this->checkDocuments();
        $total += $this->checkInspections();

        $this->info("Notificações geradas: {$total}.");

        return self::SUCCESS;
    }

    private function checkDocuments(): int
    {
        $total = 0;

        $vencidos = Nr13Document::whereDate('data_validade', '<', now())
            ->whereDate('data_validade', '>=', now()->subDays(3))
            ->get();

        foreach ($vencidos as $documento) {
            $this->notifyTenant($documento->tenant_id, new Nr13DocumentExpiringNotification($documento, 'vencido'));
            $total++;
        }

        foreach ([7 => 'vencendo_7d', 15 => 'vencendo_15d', 30 => 'vencendo_30d'] as $dias => $tipo) {
            $vencendo = Nr13Document::whereDate('data_validade', now()->addDays($dias))->get();

            foreach ($vencendo as $documento) {
                $this->notifyTenant($documento->tenant_id, new Nr13DocumentExpiringNotification($documento, $tipo));
                $total++;
            }
        }

        return $total;
    }

    private function checkInspections(): int
    {
        $total = 0;

        $vencidas = Nr13Inspection::whereDate('data_proxima_inspecao', '<', now())
            ->whereDate('data_proxima_inspecao', '>=', now()->subDays(3))
            ->get();

        foreach ($vencidas as $inspecao) {
            $this->notifyTenant($inspecao->tenant_id, new Nr13InspectionExpiringNotification($inspecao, 'vencida'));
            $total++;
        }

        foreach ([7 => 'vencendo_7d', 15 => 'vencendo_15d', 30 => 'vencendo_30d'] as $dias => $tipo) {
            $vencendo = Nr13Inspection::whereDate('data_proxima_inspecao', now()->addDays($dias))->get();

            foreach ($vencendo as $inspecao) {
                $this->notifyTenant($inspecao->tenant_id, new Nr13InspectionExpiringNotification($inspecao, $tipo));
                $total++;
            }
        }

        return $total;
    }

    private function notifyTenant(string $tenantId, $notification): void
    {
        $usuarios = User::where('tenant_id', $tenantId)->get();

        if ($usuarios->isEmpty()) {
            return;
        }

        Notification::send($usuarios, $notification);
    }
}
