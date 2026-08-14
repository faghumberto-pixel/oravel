<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Role;
use App\Models\Tenant;

/**
 * Estrutura organizacional padrão (8 setores + cargos) que todo tenant
 * recebe pronta, em vez de montar Department/Role do zero -- ver o
 * organograma de referência (documento de arquitetura "Organograma Padrão
 * Oravel", 2026-08). Chamado tanto por TenantProvisioner::provision()
 * (tenant novo) quanto pelo comando tenant:seed-org-structure (tenants
 * já existentes).
 *
 * Idempotente: Department por firstOrCreate(tenant_id+code), Role por
 * firstOrCreate(tenant_id+name), mesmo padrão de
 * database/seeders/TorresGuindastesDemoSeeder.php -- rodar de novo não
 * duplica nem sobrescreve o que o tenant já tiver ajustado.
 */
class OrganizationalStructureSeeder
{
    /**
     * Cargos que não têm hierarchy_level próprio no enum de 6 níveis
     * (Role::LEVEL_*) -- Líder/Encarregado são cargos de campo (Manutenção,
     * Logística) sem número dedicado; Vendedor Interno/Externo são trilha
     * lateral do Comercial, não fazem parte da escada vertical.
     */
    private const LEVEL_LIDER = Role::LEVEL_SUPERVISOR;

    private const LEVEL_ENCARREGADO = Role::LEVEL_TECNICO;

    private const LEVEL_VENDEDOR = Role::LEVEL_ANALISTA;

    /**
     * @return array<string, array{name: string, code: string, sector_key: string, cargos: array<int, array{name: string, level: int}>}>
     */
    public static function definitions(): array
    {
        return [
            'comercial' => [
                'name' => 'Comercial',
                'code' => 'COM',
                'sector_key' => Department::SECTOR_COMERCIAL,
                'cargos' => [
                    ['name' => 'Gerente Comercial', 'level' => Role::LEVEL_GERENTE],
                    ['name' => 'Supervisor Comercial', 'level' => Role::LEVEL_SUPERVISOR],
                    ['name' => 'Analista Comercial', 'level' => Role::LEVEL_ANALISTA],
                    ['name' => 'Assistente Comercial', 'level' => Role::LEVEL_ASSISTENTE],
                    ['name' => 'Auxiliar Comercial', 'level' => Role::LEVEL_AUXILIAR],
                    ['name' => 'Vendedor Interno', 'level' => self::LEVEL_VENDEDOR],
                    ['name' => 'Vendedor Externo', 'level' => self::LEVEL_VENDEDOR],
                ],
            ],
            'manutencao' => [
                'name' => 'Manutenção',
                'code' => 'MANUT',
                'sector_key' => Department::SECTOR_MANUTENCAO,
                'cargos' => [
                    ['name' => 'Gerente de Manutenção', 'level' => Role::LEVEL_GERENTE],
                    ['name' => 'Supervisor de Manutenção', 'level' => Role::LEVEL_SUPERVISOR],
                    ['name' => 'Analista de Manutenção', 'level' => Role::LEVEL_ANALISTA],
                    ['name' => 'Assistente de Manutenção', 'level' => Role::LEVEL_ASSISTENTE],
                    ['name' => 'Líder de Manutenção', 'level' => self::LEVEL_LIDER],
                    ['name' => 'Técnico de Manutenção', 'level' => Role::LEVEL_TECNICO],
                    ['name' => 'Encarregado de Manutenção', 'level' => self::LEVEL_ENCARREGADO],
                    ['name' => 'Auxiliar de Manutenção', 'level' => Role::LEVEL_AUXILIAR],
                ],
            ],
            'ativos_materiais' => [
                'name' => 'Ativos e Materiais',
                'code' => 'SUPR',
                'sector_key' => Department::SECTOR_SUPRIMENTOS,
                'cargos' => [
                    ['name' => 'Gerente de Ativos e Materiais', 'level' => Role::LEVEL_GERENTE],
                    ['name' => 'Supervisor de Almoxarifado', 'level' => Role::LEVEL_SUPERVISOR],
                    ['name' => 'Analista de Suprimentos', 'level' => Role::LEVEL_ANALISTA],
                    ['name' => 'Assistente de Almoxarifado', 'level' => Role::LEVEL_ASSISTENTE],
                    ['name' => 'Auxiliar de Almoxarifado', 'level' => Role::LEVEL_AUXILIAR],
                ],
            ],
            'logistica' => [
                'name' => 'Logística',
                'code' => 'LOG',
                'sector_key' => Department::SECTOR_LOGISTICA,
                'cargos' => [
                    ['name' => 'Gerente de Logística', 'level' => Role::LEVEL_GERENTE],
                    ['name' => 'Supervisor de Logística', 'level' => Role::LEVEL_SUPERVISOR],
                    ['name' => 'Analista de Logística', 'level' => Role::LEVEL_ANALISTA],
                    ['name' => 'Assistente de Logística', 'level' => Role::LEVEL_ASSISTENTE],
                    ['name' => 'Líder de Logística', 'level' => self::LEVEL_LIDER],
                    ['name' => 'Encarregado de Pátio', 'level' => self::LEVEL_ENCARREGADO],
                    ['name' => 'Auxiliar de Logística', 'level' => Role::LEVEL_AUXILIAR],
                ],
            ],
            'financeiro' => [
                'name' => 'Financeiro',
                'code' => 'FIN',
                'sector_key' => Department::SECTOR_FINANCEIRO,
                'cargos' => [
                    ['name' => 'Gerente Financeiro', 'level' => Role::LEVEL_GERENTE],
                    ['name' => 'Supervisor Financeiro', 'level' => Role::LEVEL_SUPERVISOR],
                    ['name' => 'Analista Financeiro', 'level' => Role::LEVEL_ANALISTA],
                    ['name' => 'Assistente Financeiro', 'level' => Role::LEVEL_ASSISTENTE],
                    ['name' => 'Auxiliar Financeiro', 'level' => Role::LEVEL_AUXILIAR],
                ],
            ],
            'administrativo' => [
                'name' => 'Administrativo',
                'code' => 'ADM',
                'sector_key' => Department::SECTOR_ADMINISTRATIVO,
                'cargos' => [
                    ['name' => 'Supervisor Administrativo', 'level' => Role::LEVEL_SUPERVISOR],
                    ['name' => 'Assistente Administrativo', 'level' => Role::LEVEL_ASSISTENTE],
                    ['name' => 'Auxiliar Administrativo', 'level' => Role::LEVEL_AUXILIAR],
                ],
            ],
            'departamento_pessoal' => [
                'name' => 'Departamento Pessoal',
                'code' => 'DP',
                'sector_key' => Department::SECTOR_DEPARTAMENTO_PESSOAL,
                'cargos' => [
                    ['name' => 'Gerente de Departamento Pessoal', 'level' => Role::LEVEL_GERENTE],
                    ['name' => 'Supervisor de Departamento Pessoal', 'level' => Role::LEVEL_SUPERVISOR],
                    ['name' => 'Analista de Departamento Pessoal', 'level' => Role::LEVEL_ANALISTA],
                    ['name' => 'Assistente de Departamento Pessoal', 'level' => Role::LEVEL_ASSISTENTE],
                    ['name' => 'Auxiliar de Departamento Pessoal', 'level' => Role::LEVEL_AUXILIAR],
                    // Compartilhado com Segurança do Trabalho -- criado só
                    // uma vez (chave natural é o nome), department_id fica
                    // no setor DP por ser onde o cargo é mais usado no dia
                    // a dia (ver artefato "Organograma Padrão Oravel").
                    ['name' => 'Técnico de Segurança do Trabalho', 'level' => Role::LEVEL_TECNICO],
                ],
            ],
            'seguranca_trabalho' => [
                'name' => 'Segurança do Trabalho',
                'code' => 'SESMT',
                'sector_key' => Department::SECTOR_SEGURANCA_TRABALHO,
                'cargos' => [
                    ['name' => 'Supervisor de Segurança do Trabalho', 'level' => Role::LEVEL_SUPERVISOR],
                    ['name' => 'Assistente de Segurança do Trabalho', 'level' => Role::LEVEL_ASSISTENTE],
                    // Técnico de Segurança do Trabalho já foi criado no
                    // setor Departamento Pessoal acima -- não duplica aqui.
                ],
            ],
        ];
    }

    /**
     * @return array<string, Department>
     */
    public static function seed(Tenant $tenant): array
    {
        $departments = [];

        foreach (self::definitions() as $key => $def) {
            $department = Department::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $def['code']],
                ['name' => $def['name'], 'sector_key' => $def['sector_key']]
            );

            foreach ($def['cargos'] as $cargo) {
                $role = Role::firstOrCreate(
                    ['name' => $cargo['name'], 'guard_name' => 'web', 'tenant_id' => $tenant->id],
                    ['department_id' => $department->id, 'hierarchy_level' => $cargo['level']]
                );

                // firstOrCreate só preenche os extras na criação -- se a
                // role já existia (ex: criada solta pelo TenantProvisioner
                // antes deste seed rodar) e ainda não tem department/nível,
                // completa agora sem sobrescrever o que o tenant já tiver
                // customizado manualmente.
                if ($role->department_id === null && $role->hierarchy_level === null) {
                    $role->forceFill([
                        'department_id' => $department->id,
                        'hierarchy_level' => $cargo['level'],
                    ])->save();
                }
            }

            $departments[$key] = $department;
        }

        return $departments;
    }
}
