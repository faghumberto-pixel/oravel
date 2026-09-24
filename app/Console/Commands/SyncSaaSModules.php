<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Support\SaaSRegistry;
use Illuminate\Console\Command;

class SyncSaaSModules extends Command
{
    protected $signature = 'saas:sync-modules {--plan=}';

    protected $description = 'Sync all SaaS modules from registry to plans';

    public function handle()
    {
        $registry = SaaSRegistry::modules();
        $knownFeatures = [];

        foreach ($registry as $module) {
            if ($feature = $module['feature'] ?? null) {
                $knownFeatures[] = $feature;
            }
        }

        $planId = $this->option('plan');
        $query = Plan::query();

        if ($planId) {
            $query->where('id', $planId);
        }

        $plans = $query->get();
        $newModulesTotal = 0;

        foreach ($plans as $plan) {
            // MERGE, nunca substitui: cada plano/contrato tem sua própria
            // seleção de módulos (não existe mais "plano padrão" com tudo
            // habilitado -- cada cliente negocia o próprio conjunto). Bug
            // real corrigido 2026-09-24: a versão anterior fazia
            // `$plan->update(['features' => json_encode($features)])`
            // passando uma STRING já serializada pro Attribute mutator de
            // Plan::features(), que só aceita array (senão grava '[]') --
            // isso zerava a seleção de TODO plano a cada deploy, silenciosamente,
            // desde pelo menos 2026-09-19. Este comando agora só ADICIONA
            // chaves de módulo novas (default false) que ainda não existem
            // no array do plano, preservando 100% da seleção já feita.
            $existing = $plan->features ?? [];
            $added = 0;

            foreach ($knownFeatures as $feature) {
                if (! array_key_exists($feature, $existing)) {
                    $existing[$feature] = false;
                    $added++;
                }
            }

            if ($added > 0) {
                $plan->update(['features' => $existing]);
                $newModulesTotal += $added;
                $this->info("✅ {$plan->name}: {$added} módulo(s) novo(s) adicionado(s) (desabilitados por padrão)");
            } else {
                $this->line("⏭️  {$plan->name}: nenhum módulo novo");
            }
        }

        $this->info("\n📊 Summary:");
        $this->info('  Módulos conhecidos no registry: '.count($knownFeatures));
        $this->info('  Planos verificados: '.count($plans));
        $this->info('  Novas chaves adicionadas no total: '.$newModulesTotal);
    }
}
