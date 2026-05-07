<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Limpa o cache para evitar conflitos de tipos de dados
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Lista completa com os novos recursos para gestão total
        $resources = [
            'ordem_servico',
            'checklist',
            'ativo',
            'material',
            'usuario',
            'funcao',
            'categoria',      // Adicionado
            'departamento',   // Adicionado
            'localizacao'     // Adicionado
        ];

        $actions = ['criar', 'ler', 'editar', 'excluir'];

        // 2. Garante que as permissões existam no banco com o guard correto
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$action}_{$resource}",
                    'guard_name' => 'web'
                ]);
            }
        }

        // 3. Admin: Sincroniza via IDs numéricos para evitar erros de sintaxe no Postgres
        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminPermissionIds = Permission::pluck('id')->toArray();
        $roleAdmin->permissions()->sync($adminPermissionIds);

        // 4. Técnico: Acesso operacional específico para o Pedro
        $roleTecnico = Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web']);
        $tecnicoPermissionIds = Permission::whereIn('name', [
            'ler_ordem_servico',
            'editar_ordem_servico',
            'ler_checklist',
            'ler_ativo',
            'ler_material',
            'ler_categoria',
            'ler_departamento',
            'ler_localizacao'
        ])->pluck('id')->toArray();
        
        $roleTecnico->permissions()->sync($tecnicoPermissionIds);
    }
}