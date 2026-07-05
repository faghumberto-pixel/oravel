<?php

namespace App\Observers;

use App\Models\FleetTollRecord;

class FleetTollRecordObserver
{
    public function saved(FleetTollRecord $tollRecord): void
    {
        $this->recalculate($tollRecord);
    }

    public function deleted(FleetTollRecord $tollRecord): void
    {
        $this->recalculate($tollRecord);
    }

    private function recalculate(FleetTollRecord $tollRecord): void
    {
        $tollRecord->freightRecord?->equipmentMovement?->recalculateCustoTransporte();
    }
}
