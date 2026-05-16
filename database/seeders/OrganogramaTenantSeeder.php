<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\User;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Str;

class OrganogramaTenantSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Reseta o cache de permissões do Spatie
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Recupera o primeiro Tenant e o primeiro Usuário para fazer a amarração de teste
        $tenant = Tenant::first();
        $user = User::first();

        if (!$tenant || !$user) {
            $this->command->error('Tenant ou Usuário não encontrados. Cadastre um Tenant primeiro.');
            return;
        }

        $this->command->info("Populando organograma para o Tenant: {$tenant->name}");

        // 2. CRIAR OS DEPARTAMENTOS (Garante a estrutura em UUID)
        $manutencao = Department::updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Manutenção'],
            ['id' => Str::uuid(), 'code' => 'MANUT001', 'description' => 'Oficina, pátio e manutenção corretiva/preventiva de ativos']
        );

        $operacoes = Department::updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Operações e Logística'],
            ['id' => Str::uuid(), 'code' => 'OPERA001', 'description' => 'Dimensionamento, entrega e movimentação de geradores']
        );

        // 3. MATRIZ DE FUNÇÕES POR DEPARTAMENTO (Roles do Spatie com ID de Departamento)
        
        // --- DEPARTAMENTO DE MANUTENÇÃO ---
        $funcoesManutencao = [
            'Gerente de Manutenção' => [
                'ler_ordem_servico', 'criar_ordem_servico', 'editar_ordem_servico', 'aprovar_ordem_servico', 'excluir_ordem_servico',
                'ler_ativo', 'gerenciar_ativo', 'solicitar_material', 'autorizar_material', 'ler_chat', 'criar_grupo_chat'
            ],
            'Supervisor de Manutenção' => [
                'ler_ordem_servico', 'criar_ordem_servico', 'editar_ordem_servico', 'aprovar_ordem_servico',
                'ler_ativo', 'solicitar_material', 'autorizar_material', 'ler_chat'
            ],
            'Técnico Nível 1' => [
                'ler_ordem_servico', 'ler_ativo', 'solicitar_material', 'ler_chat'
            ],
            'Técnico Nível 2' => [
                'ler_ordem_servico', 'editar_ordem_servico', 'ler_ativo', 'solicitar_material', 'ler_chat'
            ],
            'Auxiliar de Pátio' => [
                'ler_ordem_servico', 'ler_ativo', 'ler_chat'
            ]
        ];

        // --- DEPARTAMENTO DE OPERAÇÕES ---
        $funcoesOperacoes = [
            'Gerente de Operações' => [
                'ler_ordem_servico', 'criar_ordem_servico', 'editar_ordem_servico', 'aprovar_ordem_servico',
                'ler_ativo', 'gerenciar_ativo', 'ler_chat', 'criar_grupo_chat'
            ],
            'Analista de Logística' => [
                'ler_ordem_servico', 'criar_ordem_servico', 'editar_ordem_servico', 'ler_ativo', 'ler_chat'
            ],
            'Assistente Operacional' => [
                'ler_ordem_servico', 'ler_ativo', 'ler_chat'
            ]
        ];

        // 4. INJETAR NO BANCO E VINCULAR AS CAPACIDADES (Permissions)
        
        // Processa Manutenção
        foreach ($funcoesManutencao as $nomeFuncao => $permissoes) {
            $role = Role::findOrCreate($nomeFuncao, 'web');
            $role->department_id = $manutencao->id; // Amarração estrutural do organograma
            $role->save();
            
            $role->syncPermissions($permissoes);
        }

        // Processa Operações
        foreach ($funcoesOperacoes as $nomeFuncao => $permissoes) {
            $role = Role::findOrCreate($nomeFuncao, 'web');
            $role->department_id = $operacoes->id; // Amarração estrutural do organograma
            $role->save();
            
            $role->syncPermissions($permissoes);
        }

        // 5. VINCULAR O SEU USUÁRIO DE TESTE COMO 'Gerente de Manutenção' PARA VALIDAÇÃO
        // (Isso permite que você teste a visão exata do pátio com checkboxes ativos)
        $roleTeste = Role::where('name', 'Gerente de Manutenção')->first();
        if ($roleTeste) {
            $user->assignRole($roleTeste);
            // Atualiza também o job_title no cadastro do usuário
            $user->update(['job_title' => 'gerente']);
        }

        $this->command->info('Organograma corporativo semeado com sucesso!');
    }
}
