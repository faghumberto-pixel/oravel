<?php
namespace App\Console\Commands;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Console\Command;
class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync {--fresh : Drop and recreate all permissions}';
    protected $description = 'Sincroniza permissões e roles do sistema';
    public function handle()
    {
        if ($this->option('fresh')) {
            $this->info('🔄 Deletando permissões e roles...');
            Permission::truncate();
            Role::truncate();
        }
        $this->info('📝 Criando permissões...');
        $models = ['material', 'asset', 'client', 'user', 'maintenance_order', 'maintenance_task', 'report', 'dashboard'];
        $actions = ['ler', 'criar', 'editar', 'excluir'];
        $count = 0;
        foreach ($models as $model) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$action}_{$model}", 'guard_name' => 'web']);
                $count++;
            }
        }
        $this->info("✓ {$count} permissões criadas/sincronizadas");
        $this->info('👥 Criando roles...');
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());
        $this->line("  ✓ admin (32 permissões)");
        $gerente = Role::firstOrCreate(['name' => 'gerente', 'guard_name' => 'web']);
        $gerente->syncPermissions(['ler_material', 'ler_asset', 'ler_client', 'ler_maintenance_order', 'ler_maintenance_task', 'criar_material', 'editar_material', 'editar_maintenance_order']);
        $this->line("  ✓ gerente (8 permissões)");
        $tecnico = Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web']);
        $tecnico->syncPermissions(['ler_material', 'ler_asset', 'ler_maintenance_order', 'ler_maintenance_task', 'editar_maintenance_order', 'editar_maintenance_task', 'criar_maintenance_task']);
        $this->line("  ✓ tecnico (7 permissões)");
        $vendedor = Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web']);
        $vendedor->syncPermissions(['ler_material', 'ler_client', 'ler_maintenance_order']);
        $this->line("  ✓ vendedor (3 permissões)");
        $this->info('✅ Concluído!');
    }
}
