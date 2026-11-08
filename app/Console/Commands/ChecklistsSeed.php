<?php

namespace App\Console\Commands;

use App\Models\CkTemplate;
use App\Models\Tenant;
use App\Services\Checklists\ChecklistInstalador;
use Illuminate\Console\Command;

class ChecklistsSeed extends Command
{
    protected $signature = 'checklists:seed {--tenant= : id (uuid) do tenant; sem isto, todos os tenants com a chave dos checklists ligada} {--group= : so o checklist deste grupo (ex.: gerador)}';

    protected $description = 'Checklists: copia os checklists padrao por grupo de equipamento para o tenant (idempotente, nunca sobrescreve o que o tenant editou)';

    public function handle(ChecklistInstalador $instalador): int
    {
        $grupo = $this->option('group') ?: null;

        if ($id = $this->option('tenant')) {
            $tenants = Tenant::withoutGlobalScopes()->whereKey($id)->get();

            if ($tenants->isEmpty()) {
                $this->error("Tenant {$id} não encontrado.");

                return self::FAILURE;
            }
        } else {
            $tenants = Tenant::withoutGlobalScopes()->with(['plan' => fn ($q) => $q->withoutGlobalScopes()])->get()
                ->filter(fn (Tenant $t) => (bool) $t->plan?->hasFeature(CkTemplate::FEATURE_KEY));
        }

        foreach ($tenants as $tenant) {
            $r = $instalador->instalar($tenant, $grupo);
            $this->line("Tenant {$tenant->name}");
            $this->line('  Criados:   '.json_encode($r['criados']));
            $this->line('  Ignorados: '.json_encode($r['ignorados']));

            foreach ($r['avisos'] as $aviso) {
                $this->warn('  '.$aviso);
            }
        }

        if ($tenants->isEmpty()) {
            $this->info('Nenhum tenant com a chave dos checklists ligada.');
        }

        return self::SUCCESS;
    }
}
