<?php

namespace App\Observers;

use App\Models\FreightRecord;

class FreightRecordObserver
{
    public function saved(FreightRecord $freightRecord): void
    {
        $freightRecord->equipmentMovement?->recalculateCustoTransporte();
    }

    public function deleted(FreightRecord $freightRecord): void
    {
        $freightRecord->equipmentMovement?->recalculateCustoTransporte();
    }
}
