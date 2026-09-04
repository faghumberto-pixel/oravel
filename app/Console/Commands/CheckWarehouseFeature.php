<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class CheckWarehouseFeature extends Command
{
    protected $signature = 'check:warehouse-feature';
    protected $description = 'Check if warehouse feature is enabled';

    public function handle()
    {
        $tenant = Tenant::first();
        $this->info("Tenant: {$tenant?->name}");
        $this->info("Plan: {$tenant?->plan?->name}");

        if ($tenant?->plan) {
            $this->info("\n=== FEATURES DISPONÍVEIS NO PLANO ===");
            $features = $tenant->plan->getAvailableFeaturesOptions();

            foreach ($features as $key => $label) {
                if (stripos($key, 'almoxarifado') !== false ||
                    stripos($key, 'warehouse') !== false ||
                    stripos($key, 'part') !== false) {
                    $enabled = $tenant->hasFeature($key) ? "✓ ATIVO" : "✗ INATIVO";
                    $this->line("  {$enabled}: {$key}");
                }
            }
        }

        $this->info("\n=== SaaS REGISTRY ===");
        $registry = app(\App\Support\SaaSRegistry::class);
        $modules = $registry->modules();
        $warehouse = collect($modules)->firstWhere('feature_key', 'tabela_warehouses');
        if ($warehouse) {
            $this->line("✓ Warehouse Module encontrado no registry");
            $this->line("  Feature Key: " . $warehouse['feature_key']);
            $this->line("  Permission Slug: " . $warehouse['permission_slug']);
            $this->line("  Module Label: " . $warehouse['module_label']);
        } else {
            $this->error("✗ Warehouse Module NÃO encontrado no registry");
        }
    }
}
