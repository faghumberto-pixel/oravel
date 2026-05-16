<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UnifiedPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reseta cache do Spatie para evitar conflitos de memória
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Dicionário Unificado baseado nas suas Policies existentes
        $permissions = [
            // Ordens de Serviço
            'ler_ordem_servico',
            'criar_ordem_servico',
            'editar_ordem_servico',
            'excluir_ordem_servico',
            'aprovar_ordem_servico',

            // Ativos
            'ler_ativo',
            'gerenciar_ativo',

            // Materiais / Compras
            'solicitar_material',
            'autorizar_material',

            // Chat & Auditoria
            'ler_chat',
            'criar_grupo_chat',

            // Usuários e Funções (Mapeados na sua RolePolicy/UserPolicy)
            'ler_usuario', 'criar_usuario', 'editar_usuario', 'excluir_usuario',
            'ler_funcao', 'criar_funcao', 'editar_funcao', 'excluir_funcao'
        ];

        // Cria as permissões de capacidade caso não existam no banco
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // 2. Garante as 3 Roles de Visão (Alto Nível)
        Role::findOrCreate('admin', 'web'); // Seu Super Admin existente (Bypass via before)
        
        $gestor = Role::findOrCreate('gestor', 'web');
        $colaborador = Role::findOrCreate('colaborador', 'web');

        // 3. Distribuição Inteligente de Capacidades
        
        // CORREÇÃO: O Gestor do Tenant agora recebe TUDO, dando autonomia completa
        // para ele criar e gerenciar as funções/permissões dos seus próprios funcionários.
        $gestor->syncPermissions(Permission::pluck('name')->toArray());

        // O Colaborador (Mecânico/Técnico) só enxerga e opera o básico do pátio
        $colaborador->syncPermissions([
            'ler_ordem_servico',
            'ler_ativo',
            'solicitar_material',
            'ler_chat' // Permite acessar o chat para se comunicar com a equipe/gestores
        ]);
    }
}