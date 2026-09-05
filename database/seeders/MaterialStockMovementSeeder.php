<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class MaterialStockMovementSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->command->info("Populando MaterialStockMovement para: {$tenant->name}");

            $materials = Material::where('tenant_id', $tenant->id)->get();
            $user = User::where('tenant_id', $tenant->id)->first();

            if ($materials->isEmpty() || !$user) {
                $this->command->warn("Dados insuficientes para {$tenant->name}");
                continue;
            }

            $types = ['entrada', 'saída', 'ajuste'];
            $count = 0;

            foreach ($materials as $material) {
                for ($i = 0; $i < rand(8, 12); $i++) {
                    $daysAgo = rand(0, 90);
                    $date = now()->subDays($daysAgo);
                    $type = $types[array_rand($types)];

                    $quantity = match ($type) {
                        'entrada' => rand(10, 100),
                        'saída' => rand(5, 50),
                        default => rand(-20, 20),
                    };

                    MaterialStockMovement::create([
                        'tenant_id' => $tenant->id,
                        'material_id' => $material->id,
                        'type' => $type,
                        'quantity' => $quantity,
                        'balance_after' => rand(0, 500),
                        'created_by_user_id' => $user->id,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);

                    $count++;
                }
            }

            $this->command->info("✓ {$count} movimentos para {$tenant->name}");
        }

        $this->command->info('✅ MaterialStockMovement seeder concluído!');
    }
}
