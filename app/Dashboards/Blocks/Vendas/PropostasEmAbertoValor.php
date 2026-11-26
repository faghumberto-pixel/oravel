<?php

namespace App\Dashboards\Blocks\Vendas;

use App\Dashboards\Payload;
use App\Models\CrmProposta;

class PropostasEmAbertoValor extends VendasBlock
{
    public static function key(): string
    {
        return 'vendas.propostas_aberto';
    }

    public static function title(): string
    {
        return 'Propostas aguardando resposta';
    }

    public static function subtitle(): string
    {
        return 'Valor na rua agora';
    }

    public static function size(): int
    {
        return 4;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_crm_propostas';
    }

    public static function sourceModel(): string
    {
        return CrmProposta::class;
    }

    public function data(array $config): array
    {
        $abertas = CrmProposta::query()->where('status', CrmProposta::STATUS_ENVIADA);
        $n = (clone $abertas)->count();
        $valor = (float) (clone $abertas)->sum('total');
        $vencendo = (clone $abertas)->whereBetween('validade_ate', [now()->toDateString(), now()->addDays(7)->toDateString()])->count();

        return Payload::kpi($this->compacto($valor), '', $n.' propostas · '.$vencendo.' vencem em 7 dias', $vencendo > 0 ? 'dn' : 'n');
    }
}
