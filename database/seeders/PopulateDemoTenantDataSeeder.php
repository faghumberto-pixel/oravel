<?php

namespace Database\Seeders;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Asset;
use App\Models\BillCategory;
use App\Models\Client;
use App\Models\CrmLead;
use App\Models\Location;
use App\Models\MaintenanceOrder;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PopulateDemoTenantDataSeeder extends Seeder
{
    public function run()
    {
        $tenants = Tenant::whereIn('slug', ['demo-emp', 'demo-guind', 'demo-solda'])->get();

        foreach ($tenants as $tenant) {
            $this->populateTenant($tenant);
        }

        $this->command->info('\n✅ Demo tenants populated with realistic data!');
    }

    private function populateTenant(Tenant $tenant)
    {
        $this->command->info("\n📍 Populating {$tenant->name}...");

        // Locations (3)
        $locations = [];
        $locations[] = Location::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Garagem Principal'],
            ['address' => 'Av. Principal, 100', 'city' => 'Campinas', 'state' => 'SP', 'zip_code' => '13000-000']
        );
        $locations[] = Location::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Filial Centro'],
            ['address' => 'Rua das Flores, 250', 'city' => 'São Paulo', 'state' => 'SP', 'zip_code' => '01000-000']
        );
        $locations[] = Location::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Pátio Expansão'],
            ['address' => 'Rodovia SP-001, km 45', 'city' => 'Ribeirão Preto', 'state' => 'SP', 'zip_code' => '14000-000']
        );

        // Assets (15)
        $assets = [];
        for ($i = 1; $i <= 15; $i++) {
            $assets[] = Asset::create([
                'tenant_id' => $tenant->id,
                'location_id' => $locations[array_rand($locations)]->id,
                'description' => "Equipamento {$i}",
                'status' => ['em_operacao', 'em_manutencao', 'parado'][array_rand([0, 1, 2])],
                'acquisition_date' => now()->subMonths(rand(6, 48)),
            ]);
        }

        // Clients (10)
        $clients = [];
        for ($i = 1; $i <= 10; $i++) {
            $clients[] = Client::create([
                'tenant_id' => $tenant->id,
                'name' => "Cliente Demo {$i}",
                'email' => "cliente{$i}@" . str_replace('-', '', $tenant->slug) . ".br",
                'phone' => '11 9' . rand(90000000, 99999999),
            ]);
        }

        // Bill Categories (5)
        $billCats = [];
        $billCats[] = BillCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Manutenção']);
        $billCats[] = BillCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Peças']);
        $billCats[] = BillCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Serviços']);
        $billCats[] = BillCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Aluguel']);
        $billCats[] = BillCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Combustível']);

        // Maintenance Orders (20)
        for ($i = 1; $i <= 20; $i++) {
            MaintenanceOrder::create([
                'tenant_id' => $tenant->id,
                'asset_id' => $assets[array_rand($assets)]->id,
                'client_id' => $clients[array_rand($clients)]->id,
                'description' => "Manutenção {$i}",
                'status' => ['aberta', 'em_andamento', 'concluida'][array_rand([0, 1, 2])],
                'scheduled_date' => now()->addDays(rand(-30, 60)),
            ]);
        }

        // Contas a Receber (15)
        for ($i = 1; $i <= 15; $i++) {
            AccountReceivable::create([
                'tenant_id' => $tenant->id,
                'client_id' => $clients[array_rand($clients)]->id,
                'bill_category_id' => $billCats[array_rand($billCats)]->id,
                'description' => "Fatura {$i}",
                'amount' => rand(1000, 50000) / 100,
                'due_date' => now()->addDays(rand(-60, 90)),
                'status' => ['pendente', 'atrasado', 'pago'][array_rand([0, 1, 2])],
            ]);
        }

        // Contas a Pagar (10)
        for ($i = 1; $i <= 10; $i++) {
            AccountPayable::create([
                'tenant_id' => $tenant->id,
                'description' => "Conta a Pagar {$i}",
                'amount' => rand(500, 30000) / 100,
                'due_date' => now()->addDays(rand(-30, 60)),
                'status' => ['pendente', 'atrasado', 'pago'][array_rand([0, 1, 2])],
            ]);
        }

        // CRM Leads (8)
        for ($i = 1; $i <= 8; $i++) {
            CrmLead::create([
                'tenant_id' => $tenant->id,
                'name' => "Lead {$i}",
                'email' => "lead{$i}@example.com.br",
                'phone' => '11 9' . rand(90000000, 99999999),
                'status' => ['novo', 'em_contato', 'qualificado'][array_rand([0, 1, 2])],
            ]);
        }

        $this->command->info("  ✅ 15 Assets | 10 Clients | 20 OS | 15 AR | 10 AP | 8 Leads");
    }
}
