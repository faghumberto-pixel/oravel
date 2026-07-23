<?php

namespace Database\Seeders;

use App\Models\ChecklistTemplate;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Popula Checklists (App\Models\ChecklistTemplate -- catálogo simples de
 * nome/descrição/ativo, sem itens vinculados hoje) nos 5 tenants de
 * demonstração. Idempotente, aditivo, sem criar tenant novo.
 *
 * Uso: php artisan db:seed --class=ChecklistTemplateDemoSeeder
 */
class ChecklistTemplateDemoSeeder extends Seeder
{
    private const TEMPLATES = [
        ['name' => 'Checklist de Inspeção Pré-Operação', 'description' => 'Verificação obrigatória antes de qualquer operação com o equipamento.'],
        ['name' => 'Checklist de Devolução de Equipamento', 'description' => 'Vistoria completa no retorno do equipamento ao pátio.'],
        ['name' => 'Checklist de Manutenção Preventiva', 'description' => 'Itens de verificação de rotina conforme plano de manutenção.'],
        ['name' => 'Checklist de Segurança (NR-12/NR-18)', 'description' => 'Itens obrigatórios de segurança antes de liberar o equipamento pra operação.', 'is_active' => false],
    ];

    private const SLUGS = [
        'torres-guindastes',
        'geradores-rmc',
        'construtora-alicerce-locacoes',
        'eventos-show-geradores',
        'hospital-vida-plena-energia',
    ];

    public function run(): void
    {
        foreach (self::SLUGS as $slug) {
            $tenant = Tenant::where('slug', $slug)->first();

            if (! $tenant) {
                $this->command?->warn("Tenant '{$slug}' não encontrado -- pulando.");

                continue;
            }

            if (ChecklistTemplate::where('tenant_id', $tenant->id)->exists()) {
                continue;
            }

            foreach (self::TEMPLATES as $data) {
                ChecklistTemplate::create([
                    'tenant_id' => $tenant->id,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'is_active' => $data['is_active'] ?? true,
                ]);
            }
        }
    }
}
