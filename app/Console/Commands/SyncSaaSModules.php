<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Support\SaaSRegistry;
use Illuminate\Console\Command;

class SyncSaaSModules extends Command
{
    protected $signature = 'saas:sync-modules {--plan=}';

    protected $description = 'Sync all SaaS modules from registry to plans';

    public function handle()
    {
        $registry = SaaSRegistry::modules();
        $features = [];

        foreach ($registry as $module) {
            if ($feature = $module['feature'] ?? null) {
                $features[$feature] = true;
            }
        }

        $planId = $this->option('plan');
        $query = Plan::query();

        if ($planId) {
            $query->where('id', $planId);
        }

        $plans = $query->get();

        foreach ($plans as $plan) {
            $plan->update(['features' => json_encode($features)]);
            $this->info("✅ Updated plan: {$plan->name}");
        }

        $this->info("\n📊 Summary:");
        $this->info("  Total modules: " . count($features));
        $this->info("  Plans updated: " . count($plans));
    }
}
