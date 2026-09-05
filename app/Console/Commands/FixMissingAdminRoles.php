<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixMissingAdminRoles extends Command
{
    protected $signature = 'tenant:fix-missing-admin-roles {--dry-run}';

    protected $description = 'Fix tenants missing admin role assignments for their initial users';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->line('');
        $this->info('Checking for tenants with missing admin role assignments...');
        $this->line('');

        $affectedTenants = [];

        // Find all tenants where users have no admin role
        foreach (Tenant::orderBy('name')->get() as $tenant) {
            $adminCount = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->join('users', 'users.id', '=', 'model_has_roles.model_id')
                ->where('users.tenant_id', $tenant->id)
                ->where('roles.name', 'admin')
                ->where('model_has_roles.model_type', 'App\Models\User')
                ->count();

            $totalUsers = User::where('tenant_id', $tenant->id)->count();

            if ($adminCount === 0 && $totalUsers > 0) {
                $affectedTenants[] = [
                    'tenant' => $tenant,
                    'total_users' => $totalUsers,
                ];
            }
        }

        if (empty($affectedTenants)) {
            $this->line('<fg=green>✓</> No tenants found with missing admin roles.');
            $this->line('');

            return 0;
        }

        $this->line("<fg=yellow>Found " . count($affectedTenants) . ' tenant(s) with missing admin roles:</>");
        $this->line('');

        foreach ($affectedTenants as $item) {
            $tenant = $item['tenant'];
            $users = User::where('tenant_id', $tenant->id)->get();

            $this->line("  <fg=cyan>" . $tenant->name . "</> (ID: " . $tenant->id . ')');

            foreach ($users as $user) {
                $this->line("    └─ User: " . $user->name . ' (' . $user->email . ')');

                if (! $dryRun) {
                    // Create the admin role for this tenant
                    $role = Role::firstOrCreate(
                        ['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
                    );

                    // Assign the role to the user
                    $user->assignRole($role);

                    $this->line("      <fg=green>✓</> Assigned admin role (Role ID: " . $role->id . ')');
                } else {
                    $this->line("      <fg=yellow>[DRY RUN]</> Would assign admin role');
                }
            }
            $this->line('');
        }

        if ($dryRun) {
            $this->line('<fg=yellow>DRY RUN:</> Use without <fg=yellow>--dry-run</> to apply fixes.');
        } else {
            $this->line('<fg=green>✓</> All missing admin roles have been fixed.');
        }

        $this->line('');

        return 0;
    }
}
