<?php

namespace App\Console\Commands;

use App\Jobs\Aptidao\RecalcularAptidaoDiarioJob;
use App\Jobs\Aptidao\RecalcularAptidaoGrupoJob;
use Illuminate\Console\Command;

class AptidaoRecalcular extends Command
{
    protected $signature = 'aptidao:recalcular {--tenant= : so este tenant (uuid)} {--grupo= : so os ativos deste grupo (uuid)}';

    protected $description = 'G1: recalcula a aptidao dos ativos (so tenants com a chave tabela_catalogo_equipamentos ligada)';

    public function handle(): int
    {
        if ($grupo = $this->option('grupo')) {
            RecalcularAptidaoGrupoJob::dispatchSync($grupo);
        } else {
            RecalcularAptidaoDiarioJob::dispatchSync($this->option('tenant'));
        }

        $this->info('Recalculo concluido.');

        return self::SUCCESS;
    }
}
