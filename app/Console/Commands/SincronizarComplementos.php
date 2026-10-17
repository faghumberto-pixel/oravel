<?php

namespace App\Console\Commands;

use App\Services\ComplementoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SincronizarComplementos extends Command
{
    protected $signature = 'complementos:sincronizar {--hoje= : Data de referência (AAAA-MM-DD), para testes}';

    protected $description = 'Complementos: aplica cancelamentos agendados, reenvia valores pendentes da mensalidade ao Asaas e avisa o fim da fidelidade';

    public function handle(ComplementoService $servico): int
    {
        $r = $servico->rotinaDiaria($this->option('hoje') ? Carbon::parse($this->option('hoje')) : null);

        $this->info("Encerrados: {$r['encerrados']} · valores enviados: {$r['enviados']} · pendentes: {$r['pendentes']} · avisos de fidelidade: {$r['avisos']}");

        return self::SUCCESS;
    }
}
