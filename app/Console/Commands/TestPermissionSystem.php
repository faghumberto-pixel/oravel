<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;

class TestPermissionSystem extends Command
{
    protected $signature = 'test:permissions {username=Sergio}';
    protected $description = 'Testa o sistema de permissões para um usuário';

    public function handle()
    {
        $username = $this->argument('username');
        $user = User::where('name', $username)->first();
        
        if (!$user) {
            $this->error("Usuário não encontrado");
            return;
        }

        // Autentica o usuário - isso faz Tenancy::current() retornar seu tenant
        auth()->setUser($user);

        $this->info("\n========== TESTE DE PERMISSÕES ==========");
        $this->info("Usuário: {$user->name} ({$user->email})");
        $this->info("Tenant: {$user->tenant?->name}");
        $this->info("Roles: " . $user->getRoleNames()->join(', '));

        // Lista todas as permissões do usuário
        $permissions = $user->getPermissionNames();
        $this->info("\nPermissões ativas:");
        if ($permissions->isEmpty()) {
            $this->warn("  - Nenhuma");
        } else {
            foreach ($permissions as $perm) {
                $this->line("  ⬜ $perm");
            }
        }

        // Testa acesso aos módulos principais
        $modules = [
            'Material' => 'viewAny',
            'SolicitacaoLocacao' => 'viewAny',
            'User' => 'viewAny',
            'MaintenanceOrder' => 'viewAny',
            'Client' => 'viewAny',
            'Asset' => 'viewAny',
        ];

        $this->info("\n===== ACESSO AOS MÓDULOS =====");
        foreach ($modules as $model => $action) {
            $allowed = Gate::allows($action, $model);
            $status = $allowed ? '✅ PERMITE' : '❌ NEGA';
            $this->line("$status | $model::$action");
        }

        $this->info("\n===== FIM DO TESTE ==========\n");
    }
}
