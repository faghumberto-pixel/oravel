<?php

namespace App\Integracoes\Observatorio;

/** Pseudonimo estavel da empresa: HMAC-SHA256(prospect_id) com chave que so' a Central conhece. */
class EmpresaRef
{
    public static function de(string $prospectId): string
    {
        $chave = (string) config('observatorio.ref_key');
        if ($chave === '') {
            throw new ObservatorioIndisponivel('OBSERVATORIO_REF_KEY não configurada.');
        }

        return hash_hmac('sha256', $prospectId, $chave);
    }
}
