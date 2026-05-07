<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $location = Location::first();
        $tenant = Tenant::first(); 

        if ($location && $tenant) {
            Asset::create([
                'tag' => 'ATV-001',
                'name' => 'Escavadeira Hidráulica',
                'description' => 'Ativo pesado para movimentação de terra', // Obrigatório pela nova estrutura
                'patrimonio' => 'ATV-001', // Obrigatório
                'serial_number' => 'CAT123456',
                'status' => 'available',
                'criticality' => 'high',
                'current_location_id' => $location->id,
                'tenant_id' => $tenant->id,
            ]);
        }
    }
}