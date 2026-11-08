<?php

namespace App\Services\Catalogo\Aptidao;

use App\Models\Asset;
use App\Models\Nr13Inspection;
use Illuminate\Support\Carbon;

/**
 * Strategy 'nr13' (a unica regra que nao da para expressar so em dados): le a NR-13 que ja existe no sistema
 * (Nr13Inspection.data_proxima_inspecao), sem recriar estrutura. Vale a ultima inspecao de CADA tipo.
 * VALIDAR com o responsavel tecnico quais tipos de inspecao se aplicam ao grupo.
 */
class EstrategiaNr13
{
    /** Data de vencimento mais proxima entre as ultimas inspecoes de cada tipo; null = nenhuma inspecao registrada. */
    public static function proximoVencimento(Asset $ativo): ?Carbon
    {
        $ultimas = Nr13Inspection::withoutGlobalScopes()->where('tenant_id', $ativo->tenant_id)->where('asset_id', $ativo->id)
            ->whereNotNull('data_proxima_inspecao')->orderByDesc('data_inspecao')->orderByDesc('created_at')->get()->unique('tipo');

        $datas = $ultimas->pluck('data_proxima_inspecao')->filter();

        return $datas->isEmpty() ? null : Carbon::parse($datas->min());
    }

    public static function temRegistro(Asset $ativo): bool
    {
        return self::proximoVencimento($ativo) !== null;
    }
}
