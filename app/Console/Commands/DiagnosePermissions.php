<?php
namespace App\Console\Commands;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
class DiagnosePermissions extends Command
{
    protected $signature = 'permissions:diagnose {user?}';
    protected $description = 'Diagnostica permissões';
    public function handle()
    {
        $this->line('');
        $this->info('🔍 DIAGNÓSTICO DE PERMISSÕES');
        $this->line('');
        $total = Permission::count();
        $this->comment('📝 PERMISSÕES: ' . $total);
        if ($total === 0) {
            $this->line('  ❌ NENHUMA');
        } else {
            $this->line("  ✓ Total: {$total}");
        }
        $this->line('');
        $this->comment('👥 ROLES:');
        foreach (Role::all() as $role) {
            $this->line("  {$role->name}: " . $role->permissions->count() . " permissões");
        }
        $this->line('');
        if ($this->argument('user')) {
            $user = User::where('name', $this->argument('user'))->orWhere('email', $this->argument('user'))->first();
            if (!$user) {
                $this->error("  ❌ Usuário não encontrado");
                return;
            }
            $guard = auth('web');
            $guard->setUser($user);
            $this->comment("👤 TESTE: {$user->name}");
            $roles = $user->roles->pluck('name')->join(', ');
            $this->line("  Roles: " . ($roles ? $roles : 'Nenhum'));
            $this->line("  Permissões:");
            foreach (['ler_material', 'ler_asset', 'ler_client', 'ler_maintenance_order'] as $perm) {
                $has = $guard->user()->hasPermissionTo($perm);
                $icon = $has ? '✓' : '✗';
                $this->line("    {$icon} {$perm}");
            }
        }
        $this->line('');
    }
}
