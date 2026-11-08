<?php

namespace App\Console\Commands;

use App\Models\FrotaChecklist;
use App\Models\Tenant;
use App\Services\Checklists\FrotaParaChecklists;
use Illuminate\Console\Command;

class ChecklistsMigrarFrota extends Command
{
    protected $signature = 'checklists:migrar-frota {--tenant= : id (uuid) do tenant; sem isto, todos os que tem checklist da Frota} {--dry-run : mostra o que seria copiado e desfaz}';

    protected $description = 'Checklists: copia modelos e checklists de saida/retorno da Frota para o motor novo (idempotente; nada da Frota e alterado ou apagado)';

    public function handle(FrotaParaChecklists $servico): int
    {
        $simular = (bool) $this->option('dry-run');

        if ($id = $this->option('tenant')) {
            $ids = Tenant::withoutGlobalScopes()->whereKey($id)->pluck('id');

            if ($ids->isEmpty()) {
                $this->error("Tenant {$id} não encontrado.");

                return self::FAILURE;
            }
        } else {
            $ids = FrotaChecklist::withoutGlobalScopes()->distinct()->pluck('tenant_id');
        }

        foreach ($ids as $tenantId) {
            $r = $servico->migrarTenant((string) $tenantId, $simular);
            $this->line(($simular ? '[dry-run, nada gravado] ' : '').'Tenant '.(Tenant::withoutGlobalScopes()->find($tenantId)?->name ?? $tenantId));
            $this->line('  Copiados: '.json_encode(['modelos' => $r['modelos'], 'checklists' => $r['checklists']]).'  Já copiados antes: '.$r['ja_copiados']);
        }

        if ($ids->isEmpty()) {
            $this->info('Nenhum tenant com checklist da Frota.');
        }

        return self::SUCCESS;
    }
}
