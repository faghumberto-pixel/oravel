<?php

namespace App\Console\Commands;

use App\Domain\Intelligence\Services\DadosDeExemplo;
use App\Domain\Intelligence\Suprimentos\ExemploDeSuprimentos;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use Illuminate\Console\Command;

class IntelligenceExemploExpirar extends Command
{
    protected $signature = 'intelligence:exemplo-expirar';

    protected $description = 'Apaga de vez os dados de exemplo (demonstração) do Intelligence cujo prazo acabou';

    public function handle(DadosDeExemplo $exemplo): int
    {
        $apagadas = 0;
        IntelligenceTrial::withoutGlobalScopes()->whereNotNull('exemplo_oferecido_em')->each(function (IntelligenceTrial $t) use ($exemplo, &$apagadas) {
            if (DadosDeExemplo::expirou($t)) {
                $apagadas += $exemplo->limpar((string) $t->tenant_id);
            }
        });
        $this->info("Importações de exemplo apagadas: {$apagadas}");

        $cargas = 0;
        IntelligenceTrial::withoutGlobalScopes()->whereNotNull('exemplo_suprimentos_oferecido_em')->each(function (IntelligenceTrial $t) use (&$cargas) {
            if (ExemploDeSuprimentos::expirou($t)) {
                $cargas += app(ExemploDeSuprimentos::class)->limpar((string) $t->tenant_id);
            }
        });
        $this->info("Cargas de exemplo de Suprimentos apagadas: {$cargas}");

        return self::SUCCESS;
    }
}
