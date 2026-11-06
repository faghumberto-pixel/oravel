<?php

namespace App\Dashboards\Blocks\MateriaisPecas;

use App\Dashboards\Payload;
use App\Models\Material;
use App\Models\MaterialStockMovement;

class UltimasMovimentacoes extends MateriaisBlock
{
    public static function key(): string
    {
        return 'materiais.ultimas_movimentacoes';
    }

    public static function title(): string
    {
        return 'Últimas movimentações';
    }

    public static function subtitle(): string
    {
        return 'Entradas, saídas e ajustes do estoque, da mais recente para a mais antiga';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $mov = MaterialStockMovement::query()->orderByDesc('created_at')->limit(8)->get();
        $nomes = Material::query()->whereIn('id', $mov->pluck('material_id'))->pluck('name', 'id');
        $tons = [MaterialStockMovement::TYPE_ENTRADA => 'ok', MaterialStockMovement::TYPE_SAIDA => 'warn', MaterialStockMovement::TYPE_AJUSTE => 'a'];

        return Payload::timeline($mov->map(fn ($m) => [
            $m->created_at->format('d/m'), ($nomes[$m->material_id] ?? 'Material').' — '.rtrim(rtrim(number_format((float) $m->quantity, 2, ',', '.'), '0'), ','),
            ($m->reason ?: 'sem motivo informado').' · saldo '.rtrim(rtrim(number_format((float) $m->balance_after, 2, ',', '.'), '0'), ','),
            $tons[$m->type] ?? 'n', MaterialStockMovement::TYPES[$m->type] ?? null,
        ])->all(), 'Nenhuma movimentação ainda.');
    }
}
