<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MaintenanceOrderChecklist;

class MaintenanceOrderChecklistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenantId = '019dbf98-582b-71b5-ba2f-8ec7f3ac98bd';

        MaintenanceOrderChecklist::firstOrCreate([
            'item_name' => 'Verificar nível de óleo',
            'checklist_type' => 'Preventiva',
            'tenant_id' => $tenantId
        ]);
        
        MaintenanceOrderChecklist::firstOrCreate([
            'item_name' => 'Limpeza de filtros',
            'checklist_type' => 'Preventiva',
            'tenant_id' => $tenantId
        ]);
    }
}
