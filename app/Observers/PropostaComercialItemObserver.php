<?php

namespace App\Observers;

use App\Models\PropostaComercialItem;

/**
 * Mesmo padrão de QuoteItemObserver -- total_value da proposta nunca fica
 * desatualizado, sem precisar recalcular na hora de exibir.
 */
class PropostaComercialItemObserver
{
    public function saved(PropostaComercialItem $item): void
    {
        $item->propostaComercial->recalculateTotal();
    }

    public function deleted(PropostaComercialItem $item): void
    {
        $item->propostaComercial->recalculateTotal();
    }
}
