<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\OrganizationalStructureSeeder;
use Illuminate\Console\Command;

class SeedOrganizationalStructure extends Command
{
    protected $signature = 'tenant:seed-org-structure {--tenant= : UUID de um tenant específico; sem isso, roda em todos}';

    protected $description = 'Cria os 8 setores padrão (Comercial, Manutenção, Ativos e Materiais, Logística, Financeiro, Administrativo, Departamento Pessoal, Segurança do Trabalho) + cargos de cada um, para tenants que ainda não têm essa estrutura. Idempotente -- roda quantas vezes for preciso sem duplicar.';

    public function handle(): int
    {
        $tenants = $this->option('tenant')
            ? Tenant::where('id', $this->option('tenant'))->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error('Nenhum tenant encontrado.');

            return self::FAILURE;
        }

        $this->info("Aplicando estrutura organizacional em {$tenants->count()} tenant(s)...");

        foreach ($tenants as $tenant) {
            $departments = OrganizationalStructureSeeder::seed($tenant);
            $this->line("  ✓ {$tenant->name}: ".count($departments).' setores');
        }

        $this->info('Concluído.');

        return self::SUCCESS;
    }
}
