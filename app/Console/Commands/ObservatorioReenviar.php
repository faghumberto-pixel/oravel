<?php

namespace App\Console\Commands;

use App\Jobs\EnviarEventoObservatorio;
use App\Models\ObservatorioEnvio;
use Illuminate\Console\Command;

class ObservatorioReenviar extends Command
{
    protected $signature = 'observatorio:reenviar';

    protected $description = 'Reenvia ao Observatório os eventos pendentes cuja próxima tentativa já venceu (outbox).';

    public function handle(): int
    {
        $ids = ObservatorioEnvio::where('status', 'pendente')->whereNotNull('payload')
            ->where('proxima_tentativa_em', '<=', now())->orderBy('proxima_tentativa_em')->limit(100)->pluck('id');

        foreach ($ids as $id) {
            EnviarEventoObservatorio::dispatch($id);
        }
        $this->info($ids->count().' evento(s) enfileirado(s).');

        return self::SUCCESS;
    }
}
