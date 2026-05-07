<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        // Busca o Tenant criado pelo TenantSeeder para obter seu UUID real
        $tenant = Tenant::first();

        if ($tenant) {
            Location::create([
                'name' => 'Matriz Campinas',
                'address' => 'Av. Francisco Glicério, 1000',
                'city' => 'Campinas',
                'state' => 'SP',
                'zip_code' => '13000-000',
                'tenant_id' => $tenant->id, // Aqui passamos o UUID, não "1"
            ]);
        }
    }
}