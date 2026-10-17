<?php

namespace App\Console\Commands;

use App\Services\ComplementoService;
use Illuminate\Console\Command;

class AuditarComplementos extends Command
{
    protected $signature = 'complementos:auditar';

    protected $description = 'Lista contratos com módulo de complemento ligado sem complemento vigente (ligado na mão, sem valor nem fidelidade)';

    public function handle(ComplementoService $servico): int
    {
        $problemas = $servico->auditar();

        if (! $problemas) {
            $this->info('✅ Todo complemento ligado tem valor e fidelidade registrados.');

            return self::SUCCESS;
        }

        $this->table(['Cliente', 'Módulo'], array_map(fn ($p) => [$p['tenant'], $p['feature']], $problemas));
        $this->warn(count($problemas).' complemento(s) ligado(s) sem registro de cobrança.');

        return self::FAILURE;
    }
}
