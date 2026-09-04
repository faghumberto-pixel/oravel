<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class CheckUserAccess extends Command
{
    protected $signature = 'check:user-access {email?}';
    protected $description = 'Check user access for warehouse resources';

    public function handle()
    {
        $email = $this->argument('email') ?? 'admin@demo1.com.br';
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User $email NOT FOUND!");
            $this->info("\nAvailable users:");
            User::limit(10)->get()->each(fn($u) =>
                $this->line("  - {$u->email} (tenant: {$u->tenant?->name})")
            );
            return;
        }

        Auth::login($user);

        $this->info("=== USER INFO ===");
        $this->line("Email: {$user->email}");
        $this->line("Tenant: {$user->tenant?->name}");
        $this->line("Is Admin: " . ($user->isAdmin() ? "YES" : "NO"));
        $this->line("Is Super Admin: " . ($user->isSuperAdmin() ? "YES" : "NO"));

        $this->info("\n=== PERMISSIONS ===");
        $perms = ["ler_almoxarifado", "criar_almoxarifado", "editar_almoxarifado"];
        foreach ($perms as $p) {
            $has = $user->hasPermissionTo($p) ? "✓" : "✗";
            $this->line("  $has $p");
        }

        $this->info("\n=== FEATURE GATE ===");
        $feature = $user->tenant?->hasFeature('tabela_warehouses') ? "✓" : "✗";
        $this->line("$feature tabela_warehouses");

        $this->info("\n=== POLICY CHECKS ===");
        $canView = $user->can("viewAny", Warehouse::class) ? "✓" : "✗";
        $this->line("$canView Can viewAny Warehouse");

        $this->info("\n=== NAVIGATION ===");
        $this->line("WarehouseResource shouldRegisterNavigation: " .
            (\App\Filament\Resources\WarehouseResource::shouldRegisterNavigation() ? "✓" : "✗"));
        $this->line("WarehouseResource navigationGroup: " . \App\Filament\Resources\WarehouseResource::getNavigationGroup());
    }
}
