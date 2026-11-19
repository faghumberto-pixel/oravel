<?php

namespace App\Relacionamento\Services;

use App\Relacionamento\Models\RelAcaoRegra;

/** Proxima acao comercial sugerida pelo score, por regras editaveis (rel_acao_regras). Sem IA: previsivel e auditavel. */
class ProximaAcao
{
    public function para(int $score): ?RelAcaoRegra
    {
        return RelAcaoRegra::where('ativo', true)
            ->where('score_min', '<=', $score)->where('score_max', '>=', $score)
            ->orderByDesc('score_min')->first();
    }
}
