<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Definimos o ID do tenant para garantir consistência em todos os registros
        $tenantId = '019dbf98-582b-71b5-ba2f-8ec7f3ac98bd';

        // Usamos firstOrCreate passando tanto o nome quanto o tenant_id 
        // para garantir que o registro seja único dentro do escopo daquele tenant.
        Department::firstOrCreate([
            'name' => 'Manutenção',
            'tenant_id' => $tenantId
        ]);

        Department::firstOrCreate([
            'name' => 'Produção',
            'tenant_id' => $tenantId
        ]);

        Department::firstOrCreate([
            'name' => 'Qualidade',
            'tenant_id' => $tenantId
        ]);
    }
}