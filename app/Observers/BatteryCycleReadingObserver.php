<?php

namespace App\Observers;

use App\Models\BatteryCycleReading;

class BatteryCycleReadingObserver
{
    /**
     * Mantem Asset.battery_cycles_atual em sincronia -- mesmo criterio
     * "nunca regride" de HorimeterReadingObserver::created(), sem isso
     * MaintenancePlan::dueStatusForAsset() ficaria cego pros apontamentos
     * feitos so' por aqui.
     */
    public function created(BatteryCycleReading $reading): void
    {
        $asset = $reading->asset;

        if ($asset && $reading->cycles >= (int) $asset->battery_cycles_atual) {
            $asset->updateQuietly(['battery_cycles_atual' => $reading->cycles]);
        }
    }
}
