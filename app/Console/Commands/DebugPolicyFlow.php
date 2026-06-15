<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\SaaSRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DebugPolicyFlow extends Command
{
    protected $signature = 'debug:policy {username=Sergio} {model=Material}';
    protected $description = 'Debug detalhado do fluxo da AbstractPolicy';

    public function handle()
    {
        $username = $this->argument('username');
        $modelName = $this->argument('model');
        
        $user = User::where('name', $username)->first();
        if (!$user) {
            $this->error("Usuário não encontrado");
            return;
        }

        $modelClass = "App\\Models\\$modelName";
        
        $this->info("\n========== DEBUG POLICY ==========");
        $this->info("Usuário: {$user->name}");
        $this->info("Tenant: {$user->tenant?->name}");
        $this->info("Verificando: $modelName::viewAny");

        // Passo 1: isSuperAdmin
        $isSuperAdmin = $user->isSuperAdmin();
        $this->line("\n1. isSuperAdmin() = " . ($isSuperAdmin ? 'TRUE' : 'FALSE'));

        // Passo 2: Trava comercial
        $tenant = $user->tenant;
        $plan = $tenant?->plan;
        
        // Calcula a feature key (copiado da lógica da policy)
        $featureKey = $this->getFeatureKey($modelClass);
        $hasFeature = $plan?->hasFeature($featureKey) ?? false;
        
        $this->line("\n2. TRAVA COMERCIAL:");
        $this->line("   Plan: " . ($plan?->name ?? 'NULL'));
        $this->line("   Feature procurada: '$featureKey'");
        $this->line("   hasFeature() = " . ($hasFeature ? 'TRUE' : 'FALSE'));
        
        if ($plan && $hasFeature) {
            $this->line("   ✓ Plano permite - PASSA");
        } else {
            $this->line("   ✗ Plano NÃO permite - NEGA AQUI");
            $this->info("\n========== FIM ==========\n");
            return;
        }

        // Passo 3: Admin bypass
        $isAdmin = $user->isAdmin();
        $this->line("\n3. ADMIN BYPASS:");
        $this->line("   isAdmin() = " . ($isAdmin ? 'TRUE' : 'FALSE'));
        if ($isAdmin) {
            $this->line("   ✓ Admin - RETORNA TRUE");
            $this->info("\n========== FIM ==========\n");
            return;
        }

        // Passo 4: Permissão individual
        $permName = $this->getPermissionName('viewAny', $modelClass);
        $hasPerm = $user->hasPermissionTo($permName);
        $this->line("\n4. PERMISSÃO INDIVIDUAL:");
        $this->line("   Permissão procurada: '$permName'");
        $this->line("   hasPermissionTo() = " . ($hasPerm ? 'TRUE' : 'FALSE'));
        
        $this->info("\n========== FIM ==========\n");
    }

    private function getFeatureKey($modelClass): ?string
    {
        if (!class_exists($modelClass)) {
            return null;
        }

        // 1) Fonte única de verdade
        if ($meta = SaaSRegistry::forModel($modelClass)) {
            return $meta['feature'];
        }

        // 2) Fallback legado
        $slug = Str::snake(class_basename($modelClass));
        $featureMap = [
            'role' => 'tabela_roles',
            'permission' => 'tabela_roles',
            'material' => 'tabela_materials',
            'asset' => 'tabela_assets',
            'client' => 'tabela_clients',
            'user' => 'tabela_users',
            'maintenance_order' => 'tabela_maintenance_orders',
        ];
        return $featureMap[$slug] ?? null;
    }

    private function getPermissionName(string $action, $modelClass): ?string
    {
        $prefix = match ($action) {
            'viewAny', 'view' => 'ler',
            'create'          => 'criar',
            'update'          => 'editar',
            'delete'          => 'excluir',
            default           => $action
        };

        if (!class_exists($modelClass)) {
            return null;
        }

        // 1) Fonte única de verdade
        if ($meta = SaaSRegistry::forModel($modelClass)) {
            return "{$prefix}_{$meta['slug']}";
        }

        // 2) Fallback legado
        $slugName = Str::snake(class_basename($modelClass));
        $map = [
            'role'        => 'funcao',
            'material'    => 'material',
            'asset'       => 'asset',
            'client'      => 'client',
        ];
        $suffix = $map[$slugName] ?? $slugName;

        return "{$prefix}_{$suffix}";
    }
}
