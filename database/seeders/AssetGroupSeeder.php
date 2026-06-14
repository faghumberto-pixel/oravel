<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChecklistGroup;
use Illuminate\Support\Str;

class AssetGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define o mesmo ID de tenant utilizado nos outros Seeders
        $tenantId = '019dbf98-582b-71b5-ba2f-8ec7f3ac98bd';

        // Usamos o segundo array do firstOrCreate para passar o ID apenas na criação do registro
        ChecklistGroup::firstOrCreate(
            [
                'name' => 'Linha de Envase',
                'tenant_id' => $tenantId
            ],
            [
                'id' => Str::uuid()->toString() // Resolve a restrição NOT NULL gerando o UUID
            ]
        );

        ChecklistGroup::firstOrCreate(
            [
                'name' => 'Frota Veicular',
                'tenant_id' => $tenantId
            ],
            [
                'id' => Str::uuid()->toString() // Resolve a restrição NOT NULL gerando o UUID
            ]
        );
    }
}