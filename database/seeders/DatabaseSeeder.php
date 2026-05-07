<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Executa os outros Seeders criados
        $this->call([
            DepartmentSeeder::class,
            AssetGroupSeeder::class,
            AssetSeeder::class,
            MaterialCategorySeeder::class,
            MaterialSeeder::class,
            MaintenanceOrderChecklistSeeder::class,
            // UserSeeder::class, // Comentei pois você já criou os usuários aqui embaixo
        ]);

        // 2. Garante os Tenants
        $tenant1 = Tenant::firstOrCreate(['id' => '019dbf98-582b-71b5-ba2f-8ec7f3ac98bd'], ['name' => 'Campinas Tech']);
        $tenant2 = Tenant::firstOrCreate(['id' => '019dbf98-596c-7341-be3b-5e6519c244e3'], ['name' => 'Vira Virou']);

        // 3. Cria Gestor Tech
        User::updateOrCreate(
            ['email' => 'admin@campinastech.com'],
            [
                'name' => 'Gestor Tech',
                'password' => Hash::make('password123'),
                'tenant_id' => $tenant1->id,
            ]
        );

        // 4. Cria Gestor Log
        User::updateOrCreate(
            ['email' => 'admin@viravirou.com'],
            [
                'name' => 'Gestor Log',
                'password' => Hash::make('password123'),
                'tenant_id' => $tenant2->id,
            ]
        );
    }
}