<?php

namespace Database\Seeders;

use App\Models\MaintenanceOrder;
use App\Models\Asset;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class MaintenanceOrderSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('name', 'Nova Locadora SA')->first();
        if (!$tenant) return;

        $assets = Asset::where('tenant_id', $tenant->id)->get();
        
        $maintenanceTypes = ['Corretiva', 'Preventiva', 'Check-in', 'Check-out'];
        $statuses = ['Concluída', 'Em Andamento', 'Agendada'];
        $naturezas = ['Interno', 'Externo/Alugado'];

        foreach ($assets as $index => $asset) {
            $horimetroAnterior = rand(1000, 5000);
            $horimetroAtual = $horimetroAnterior + rand(100, 500);

            MaintenanceOrder::create([
                'tenant_id' => $tenant->id,
                'asset_id' => $asset->id,
                'os_number' => 'OS-062026-' . str_pad($index + 1, 5, '0', STR_PAD_LEFT),
                'maintenance_type' => $maintenanceTypes[array_rand($maintenanceTypes)],
                'natureza_servico' => $naturezas[array_rand($naturezas)],
                'status' => $statuses[array_rand($statuses)],
                'priority' => rand(1, 5),
                'description' => "Manutenção do ativo: {$asset->name}",
                'technical_notes' => 'Serviço executado conforme cronograma de manutenção preventiva.',
                'horimetro_anterior' => $horimetroAnterior,
                'horimetro_atual' => $horimetroAtual,
                'labor_cost' => rand(50, 500),
                'material_cost' => rand(20, 300),
                'logistics_cost' => rand(10, 150),
                'total_order_cost' => 0,
                'created_at' => now()->subDays(rand(0, 30)),
                'updated_at' => now()->subDays(rand(0, 30)),
            ]);
        }
        
        echo "✓ 5 Ordens de Serviço criadas com sucesso!\n";
    }
}
