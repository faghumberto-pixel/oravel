<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Console\Command;

class FixDemoDashboardAccess extends Command
{
    protected $signature = 'tenant:fix-demo-dashboard-access {--dry-run}';

    protected $description = 'Fix demo tenants missing modulo_dashboard feature and plan assignments';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->line('');
        $this->info('Fixing demo tenant dashboard access issues...');
        $this->line('');

        // 1. Ensure "Plano Demo Comercial" has modulo_dashboard
        $demoPlan = Plan::where('name', 'Plano Demo Comercial')->first();

        if (! $demoPlan) {
            $this->error('ERROR: "Plano Demo Comercial" not found');

            return 1;
        }

        $features = $demoPlan->features ?? [];
        if (! is_array($features)) {
            $features = (array) $features;
        }

        if (! in_array('modulo_dashboard', $features, true)) {
            $this->line("Adding modulo_dashboard feature to 'Plano Demo Comercial'...");

            if (! $dryRun) {
                $features[] = 'modulo_dashboard';
                $demoPlan->features = $features;
                $demoPlan->save();
                $this->line("<fg=green>✓</> modulo_dashboard added to plan");
            } else {
                $this->line("<fg=yellow>[DRY RUN]</> Would add modulo_dashboard");
            }
        }

        // 2. Ensure tenants without plan_id get assigned the demo plan
        $this->line('');
        $this->line('Checking tenant plan assignments...');

        $tenantsWithoutPlan = Tenant::whereNull('plan_id')->get();

        if ($tenantsWithoutPlan->isNotEmpty()) {
            foreach ($tenantsWithoutPlan as $tenant) {
                $this->line("Tenant: <fg=cyan>" . $tenant->name . "</> (no plan)");

                if (! $dryRun) {
                    $tenant->update(['plan_id' => $demoPlan->id]);
                    $this->line("  <fg=green>✓</> Assigned Plano Demo Comercial");
                } else {
                    $this->line("  <fg=yellow>[DRY RUN]</> Would assign Plano Demo Comercial");
                }
            }
        } else {
            $this->line('All tenants have plan assignments.');
        }

        // 3. Verify all demo tenants now have dashboard access
        $this->line('');
        $this->line('Verification - all tenants:');
        $this->line('');

        $allTenants = Tenant::orderBy('name')->get();

        foreach ($allTenants as $tenant) {
            $hasDashboard = $tenant->hasFeature('modulo_dashboard');
            $status = $hasDashboard ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  $status {$tenant->name} - modulo_dashboard: " . ($hasDashboard ? 'YES' : 'NO'));
        }

        $this->line('');

        if ($dryRun) {
            $this->line('<fg=yellow>DRY RUN:</> Use without <fg=yellow>--dry-run</> to apply fixes.');
        } else {
            $this->line('<fg=green>✓</> Dashboard access fixes completed.');
        }

        $this->line('');

        return 0;
    }
}
