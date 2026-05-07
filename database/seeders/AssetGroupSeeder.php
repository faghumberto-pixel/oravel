<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChecklistGroup;

class AssetGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define o mesmo ID de tenant utilizado nos outros Seeders
        $tenantId = '019dbf98-582b-71b5-ba2f-8ec7f3ac98bd';

        // Incluímos o tenant_id para satisfazer a restrição do banco de dados (NOT NULL)
        ChecklistGroup::firstOrCreate([
            'name' => 'Linha de Envase',
            'tenant_id' => $tenantId
        ]);

        ChecklistGroup::firstOrCreate([
            'name' => 'Frota Veicular',
            'tenant_id' => $tenantId
        ]);
    }
}