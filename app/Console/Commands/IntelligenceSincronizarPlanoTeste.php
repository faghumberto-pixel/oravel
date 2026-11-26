<?php

namespace App\Console\Commands;

use App\Domain\Intelligence\Trial\Services\TrialCadastro;
use Illuminate\Console\Command;

class IntelligenceSincronizarPlanoTeste extends Command
{
    protected $signature = 'intelligence:sync-plano-teste';

    protected $description = 'Alinha o contrato interno do teste grátis do Intelligence com os módulos atuais do pacote (hoje só acontece num novo cadastro)';

    public function handle(TrialCadastro $cadastro): int
    {
        $plano = $cadastro->planoDeTeste();
        $this->info('Contrato "'.$plano->name.'" com '.count(array_filter((array) $plano->features)).' módulos ativos.');

        return self::SUCCESS;
    }
}
