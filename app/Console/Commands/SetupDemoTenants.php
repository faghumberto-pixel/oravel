<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetupDemoTenants extends Command
{
    protected $signature = 'tenant:setup-demos';
    protected $description = 'Delete all tenants and create 3 demo tenants with data';

    public function handle(): int
    {
        if (!$this->confirm('⚠️ This will DELETE ALL tenants and create 3 demos. Continue?')) {
            return 1;
        }

        // 1. Delete all tenants
        $this->info('🗑️ Deleting all tenants...');
        Tenant::query()->forceDelete();
        $this->info('✅ All tenants deleted');

        // 2. Create demo tenants
        $demos = [
            ['slug' => 'demo_empilhadeiras', 'name' => 'Demo Empilhadeiras', 'email' => 'admin@demo1.com.br', 'password' => 'Demo1oravel*'],
            ['slug' => 'demo_guindastes', 'name' => 'Demo Guindastes', 'email' => 'admin@demo2.com.br', 'password' => 'Demo2oravel*'],
            ['slug' => 'demo_solda', 'name' => 'Demo Solda', 'email' => 'admin@demo3.com.br', 'password' => 'Demo3oravel*'],
        ];

        foreach ($demos as $demo) {
            $this->info("📍 Creating tenant: {$demo['name']}...");

            $tenant = Tenant::create([
                'slug' => $demo['slug'],
                'name' => $demo['name'],
                'segment' => null,
            ]);

            // Create admin user
            User::create([
                'name' => 'Admin',
                'email' => $demo['email'],
                'password' => Hash::make($demo['password']),
                'tenant_id' => $tenant->id,
            ]);

            $this->info("✅ Tenant created: {$demo['slug']}");
        }

        // 3. Run seeders for each tenant
        $this->info('🌱 Running seeders...');
        foreach (Tenant::all() as $tenant) {
            $this->info("📍 Seeding {$tenant->slug}...");

            $this->artisan('db:seed', [
                '--database' => 'tenant',
            ], $tenant);

            $this->info("✅ Seeded {$tenant->slug}");
        }

        $this->info('✅ Demo tenants created and seeded!');
        return 0;
    }

    protected function artisan($command, $parameters = [], $tenant = null)
    {
        if ($tenant) {
            config(['tenancy.current_tenant' => $tenant]);
        }

        return $this->call($command, array_merge(
            $parameters,
            ['--database' => 'tenant']
        ));
    }
}
