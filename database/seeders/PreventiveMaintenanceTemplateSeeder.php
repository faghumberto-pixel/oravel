<?php

namespace Database\Seeders;

use App\Models\ChecklistGroup;
use App\Models\MaintenancePlan;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Template de Manutencao Preventiva por Grupo de Ativo (horas trabalhadas).
 * Cada linha vira um MaintenancePlan com checklist_group_id preenchido e
 * asset_id nulo -- vale pra todo Ativo daquele grupo, em todos os tenants
 * (mesmo padrao do BasicChecklistTemplateSeeder: um ChecklistGroup por
 * tenant, um MaintenancePlan-template por combinacao tenant+grupo+item).
 */
class PreventiveMaintenanceTemplateSeeder extends Seeder
{
    private const ITEMS_BY_GROUP = [
        'Geradores de Energia' => [
            ['name' => 'Troca de óleo do motor', 'interval_hours' => 250],
            ['name' => 'Troca de filtro de óleo', 'interval_hours' => 250],
            ['name' => 'Troca de filtro de ar', 'interval_hours' => 500],
            ['name' => 'Troca de filtro de combustível', 'interval_hours' => 500],
            ['name' => 'Verificação/troca de correias', 'interval_hours' => 1000],
            ['name' => 'Análise de fluido de arrefecimento', 'interval_hours' => 1000],
            ['name' => 'Teste de bateria', 'interval_hours' => 500],
        ],
        'Compressores de Ar' => [
            ['name' => 'Troca de óleo do compressor', 'interval_hours' => 500],
            ['name' => 'Troca de filtro separador de óleo', 'interval_hours' => 1000],
            ['name' => 'Troca de filtro de admissão', 'interval_hours' => 500],
            ['name' => 'Teste de válvula de segurança', 'interval_hours' => 1000, 'notes' => 'Obrigatório NR-13'],
            ['name' => 'Verificação de correias', 'interval_hours' => 500],
        ],
        'Plataformas Elevatórias' => [
            ['name' => 'Lubrificação geral', 'interval_hours' => 250],
            ['name' => 'Verificação de nível de óleo hidráulico', 'interval_hours' => 250],
            ['name' => 'Troca de óleo hidráulico', 'interval_hours' => 1000],
            ['name' => 'Inspeção de cabos/correntes', 'interval_hours' => 500],
            ['name' => 'Teste de sistema de emergência', 'interval_hours' => 500],
        ],
        'Caminhão Munck' => [
            ['name' => 'Troca de óleo hidráulico', 'interval_hours' => 500],
            ['name' => 'Lubrificação de lança e cabos', 'interval_hours' => 250],
            ['name' => 'Inspeção de cabo de aço', 'interval_hours' => 250, 'notes' => 'Crítico de segurança'],
            ['name' => 'Revisão de freios de giro', 'interval_hours' => 1000],
        ],
        'Guindastes' => [
            ['name' => 'Troca de óleo hidráulico', 'interval_hours' => 500],
            ['name' => 'Lubrificação de lança e cabos', 'interval_hours' => 250],
            ['name' => 'Inspeção de cabo de aço', 'interval_hours' => 250, 'notes' => 'Crítico de segurança'],
            ['name' => 'Revisão de freios de giro', 'interval_hours' => 1000],
        ],
    ];

    public function run(): void
    {
        Tenant::all()->each(function (Tenant $tenant) {
            foreach (self::ITEMS_BY_GROUP as $groupName => $items) {
                $group = ChecklistGroup::where('tenant_id', $tenant->id)->where('name', $groupName)->first();

                if (! $group) {
                    continue;
                }

                foreach ($items as $item) {
                    MaintenancePlan::firstOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'checklist_group_id' => $group->id,
                            'name' => $item['name'],
                        ],
                        [
                            'interval_hours' => $item['interval_hours'],
                            'notes' => $item['notes'] ?? null,
                            'is_active' => true,
                        ]
                    );
                }
            }
        });
    }
}
