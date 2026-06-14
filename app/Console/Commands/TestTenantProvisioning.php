<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\Plan;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestTenantProvisioning extends Command
{
    protected $signature = 'test:provision-tenant {name} {plan_name}';
    protected $description = 'Simula a criação de um novo Tenant para testar o Observer e funcionalidades';

    public function handle()
    {
        $name = $this->argument('name');
        $planName = $this->argument('plan_name');

        $plan = Plan::where('name', $planName)->first();

        if (!$plan) {
            $this->error("Plano '{$planName}' não encontrado!");
            $this->warn("Planos disponíveis no banco de dados:");
            $this->table(['ID', 'Nome'], Plan::all(['id', 'name'])->toArray());
            return;
        }

        $this->info("Iniciando provisionamento para: {$name} com o plano: {$plan->name}...");

        // Adicionado time() ao slug para garantir unicidade em cada teste
        $slug = Str::slug($name) . '-' . time();

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'plan_id' => $plan->id,
            'status' => 'active', // Garantindo status ativo para o hasAccess funcionar
        ]);

        $this->info("Tenant criado com sucesso (ID: {$tenant->id}).");
        
        // Verificar se os departamentos foram criados pelo Observer
        $deptCount = $tenant->departments()->count();
        $this->info("Departamentos provisionados: {$deptCount}");

        // Verificar se os roles foram criados pelo Observer
        $roleCount = $tenant->roles()->count();
        $this->info("Roles provisionadas: {$roleCount}");

        $this->info("Processo concluído com sucesso!");
    }
}