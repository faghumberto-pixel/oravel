<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class EnableDashboardFeature extends Command
{
    protected $signature = 'feature:enable-dashboard {email}';

    protected $description = 'Enable modulo_dashboard feature on a user tenant plan';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User not found: $email");
            return 1;
        }

        $tenant = $user->tenant;
        if (!$tenant) {
            $this->error("No tenant found for user");
            return 1;
        }

        $plan = $tenant->plan;
        if (!$plan) {
            $this->error("No plan found for tenant");
            return 1;
        }

        $features = is_string($plan->features) ? json_decode($plan->features, true) : [];
        if (!is_array($features)) {
            $features = [];
        }

        $features['modulo_dashboard'] = true;
        $plan->update(['features' => $features]);

        $this->info("✅ modulo_dashboard enabled for tenant: {$tenant->name}");
        $this->info("Plan: {$plan->name}");

        return 0;
    }
}
