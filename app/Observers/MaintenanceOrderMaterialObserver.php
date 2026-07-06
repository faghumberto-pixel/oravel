<?php

namespace App\Observers;

use App\Models\MaintenanceOrderMaterial;
use App\Services\MaterialConsumptionService;

class MaintenanceOrderMaterialObserver
{
    public function created(MaintenanceOrderMaterial $maintenanceOrderMaterial): void
    {
        app(MaterialConsumptionService::class)->recordConsumption($maintenanceOrderMaterial);
    }
}
