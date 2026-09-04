<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class EnableWarehouseFeatures extends Command
{
    protected $signature = 'warehouse:enable-features {tenant?}';
    protected $description = 'Enable warehouse features for a tenant';

    public function handle()
    {
        $tenantId = $this->argument('tenant');
        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        $features = [
            'tabela_warehouses' => true,
            'tabela_parts' => true,
            'tabela_stock_movements' => true,
        ];

        foreach ($tenants as $tenant) {
            $this->info("Habilitando features para: {$tenant->name}");

            // Check if features column exists
            if (!Schema::hasColumn('tenants', 'features')) {
                $this->error("Coluna 'features' não existe na tabela tenants!");
                continue;
            }

            $currentFeatures = $tenant->features ?? [];
            $updatedFeatures = array_merge($currentFeatures, $features);
            $tenant->update(['features' => $updatedFeatures]);

            $this->line("  ✓ Features habilitadas: " . implode(', ', array_keys($features)));
        }

        $this->info("✅ Concluído!");
    }
}
