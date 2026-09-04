<?php

namespace App\Console\Commands;

use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class DebugWarehouseNav extends Command
{
    protected $signature = 'debug:warehouse-nav';
    protected $description = 'Debug why warehouse is not showing in navigation';

    public function handle()
    {
        $user = User::where('email', 'admin@demo1.com.br')->first();
        Auth::login($user);

        $this->info("=== DEBUG WAREHOUSE NAVIGATION ===\n");
        $this->line("User: " . $user->email);
        $this->line("Tenant: " . $user->tenant?->name);
        $this->line("Is Admin: " . ($user->isAdmin() ? 'YES' : 'NO'));

        $this->info("\n=== WAREHOUSE RESOURCE ===");
        $resource = 'App\Filament\Resources\WarehouseResource';
        $this->line("Resource: $resource");
        $this->line("Model: " . (class_exists($resource) ? "✓ EXISTS" : "✗ NOT FOUND"));

        if (class_exists($resource)) {
            $this->line("Navigation Group: " . ($resource::getNavigationGroup() ?? 'NONE'));
            $this->line("Navigation Sort: " . $resource::getNavigationSort());
            $this->line("Label: " . $resource::getModelLabel());
        }

        $this->info("\n=== POLICY CHECKS ===");
        $this->line("User can viewAny Warehouse: " . ($user->can('viewAny', Warehouse::class) ? "✓ YES" : "✗ NO"));
        $this->line("User can create Warehouse: " . ($user->can('create', Warehouse::class) ? "✓ YES" : "✗ NO"));

        $this->info("\n=== PERMISSIONS ===");
        $perms = ['ler_almoxarifado', 'criar_almoxarifado', 'editar_almoxarifado'];
        foreach ($perms as $perm) {
            $this->line("Has $perm: " . ($user->hasPermissionTo($perm) ? "✓ YES" : "✗ NO"));
        }

        $this->info("\n=== FEATURE CHECK ===");
        $this->line("Tenant has feature tabela_warehouses: " . ($user->tenant?->hasFeature('tabela_warehouses') ? "✓ YES" : "✗ NO"));

        $this->info("\n=== SOLUTION ===");
        $this->line("Try clearing Filament cache:");
        $this->line("  php artisan filament:cache-manifest");
        $this->line("  php artisan cache:clear");
    }
}
