<?php

namespace App\Console\Commands;

use App\Models\CrmProposta;
use Illuminate\Console\Command;

/** Propostas do CRM enviadas cuja validade já passou viram "expiradas" (não aceitam mais resposta). */
class ExpirarPropostasCrm extends Command
{
    protected $signature = 'crm:expirar-propostas';

    protected $description = 'Marca como expiradas as propostas do CRM enviadas com a validade vencida';

    public function handle(): int
    {
        $n = CrmProposta::withoutGlobalScopes()
            ->where('status', CrmProposta::STATUS_ENVIADA)
            ->whereNotNull('validade_ate')
            ->whereDate('validade_ate', '<', now()->toDateString())
            ->update(['status' => CrmProposta::STATUS_EXPIRADA]);

        $this->info("{$n} proposta(s) expirada(s).");

        return self::SUCCESS;
    }
}
