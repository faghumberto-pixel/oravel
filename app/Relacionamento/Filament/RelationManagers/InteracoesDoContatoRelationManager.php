<?php

namespace App\Relacionamento\Filament\RelationManagers;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** Linha do tempo na ficha do CONTATO (pessoa): mesma tabela da aba da empresa, so' que pelas interacoes da pessoa. */
class InteracoesDoContatoRelationManager extends RelacionamentoRelationManager
{
    protected static string $relationship = 'interacoes';

    protected function interacoes(): HasMany
    {
        return $this->getOwnerRecord()->interacoes();
    }

    protected function mostraInteresses(): bool
    {
        return false;
    }
}
