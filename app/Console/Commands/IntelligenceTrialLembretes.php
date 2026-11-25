<?php

namespace App\Console\Commands;

use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Services\TrialLembretes;
use Illuminate\Console\Command;

/** Diário: atualiza o status das contas de teste e envia os lembretes dos dias 20, 27 e 30 (sem duplicar). */
class IntelligenceTrialLembretes extends Command
{
    protected $signature = 'intelligence:trial-lembretes';

    protected $description = 'Atualiza o status dos testes do Intelligence e envia os lembretes (dia 20, 27 e 30)';

    public function handle(TrialLembretes $lembretes): int
    {
        IntelligenceTrial::withoutGlobalScopes()->each(fn (IntelligenceTrial $t) => $t->sincronizarStatus());

        $r = $lembretes->executar();
        $this->info("Lembretes enviados: {$r['enviados']}; sem canal de envio: {$r['sem_canal']}");

        return self::SUCCESS;
    }
}
