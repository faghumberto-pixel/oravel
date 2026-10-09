<?php

namespace App\Services;

use App\Models\AccountReceivable;
use App\Models\AggregateItemExit;
use App\Models\AggregateItemType;
use App\Models\Contract;
use App\Models\SpecializedService;

/**
 * Custo real de um contrato, por categoria, contra o que já foi faturado.
 * Fontes: Mão de Obra Especializada (cost, exceto cancelados) e saídas de
 * Acessórios / Insumos (quantidade x custo unitário congelado na saída).
 * Segurança e Documentação não tem custo cadastrado hoje, então aparece
 * zerada. Itens devolvidos em estado OK não contam como custo.
 */
class ContractCostService
{
    /**
     * @return array{lines: array<string, float>, cost: float, billed: float, margin: float, margin_pct: ?float}
     */
    public function summary(Contract $contract): array
    {
        $maoDeObra = (float) SpecializedService::where('contract_id', $contract->id)
            ->where('status', '!=', 'cancelado')->sum('cost');

        $saidas = AggregateItemExit::with('type')->where('contract_id', $contract->id)
            ->where(function ($q) {
                $q->where('returned', false)->orWhere('returned_condition', '!=', AggregateItemExit::CONDITION_OK);
            })->get();

        $custoSaidas = fn (string $categoria) => round((float) $saidas
            ->filter(fn (AggregateItemExit $s) => $s->type?->category === $categoria)
            ->sum(fn (AggregateItemExit $s) => $s->quantity * (float) $s->unit_cost), 2);

        $lines = [
            'mao_de_obra' => round($maoDeObra, 2),
            'seguranca_documentacao' => 0.0,
            'acessorio' => $custoSaidas(AggregateItemType::CATEGORY_ACESSORIO),
            'insumo' => $custoSaidas(AggregateItemType::CATEGORY_INSUMO),
        ];

        $cost = round(array_sum($lines), 2);
        $billed = round((float) AccountReceivable::where('contract_id', $contract->id)
            ->where('status', '!=', 'cancelado')->sum('amount'), 2);
        $margin = round($billed - $cost, 2);

        return [
            'lines' => $lines,
            'cost' => $cost,
            'billed' => $billed,
            'margin' => $margin,
            'margin_pct' => $billed > 0 ? round($margin / $billed * 100, 1) : null,
        ];
    }
}
