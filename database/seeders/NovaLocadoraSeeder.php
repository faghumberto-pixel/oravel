<?php

namespace Database\Seeders;

use App\Models\{Tenant, User, Asset, ServiceOrder, Checklist, Material, Supplier};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class NovaLocadoraSeeder extends Seeder
{
    public function run(): void
    {
        // Criação do Tenant
        $tenant = Tenant::firstOrCreate(['name' => 'Nova Locadora SA']);

        // Admin
        User::firstOrCreate(['email' => 'admin@novalocadora.com.br'], [
            'name' => 'Admin Nova Locadora',
            'password' => Hash::make('password123'),
            'tenant_id' => $tenant->id,
        ]);

        // Populando dados
        Supplier::factory()->count(5)->create(['tenant_id' => $tenant->id]);
        Material::factory()->count(10)->create(['tenant_id' => $tenant->id]);
        $assets = Asset::factory()->count(20)->create(['tenant_id' => $tenant->id]);
        $checklists = Checklist::factory()->count(5)->create(['tenant_id' => $tenant->id]);

        ServiceOrder::factory()->count(15)->create([
            'tenant_id' => $tenant->id,
            'asset_id' => $assets->random()->id,
            'checklist_id' => $checklists->random()->id,
        ]);
    }
}
