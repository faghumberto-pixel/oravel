<?php

namespace App\Services\Estoque;

use App\Models\MaterialStockMovement;
use App\Models\StockMovement;
use App\Models\StockPlacement;
use Illuminate\Support\Facades\DB;

/**
 * Mantem a quantidade de cada endereco (prateleira) acompanhando o estoque: quando um item e' consumido, ajustado ou recebido,
 * o endereco onde ele esta' tambem muda. Consumo sai primeiro do endereco com mais unidades; entrada soma no endereco que ja tem
 * mais unidades do item. Item sem endereco nao e' alterado (fica na pendencia "sem endereco"). A contagem fisica desliga este
 * acompanhamento enquanto finaliza, porque ela mesma grava a quantidade contada em cada endereco.
 */
class EstoqueEnderecos
{
    /** Ligado pela contagem fisica ao finalizar: os ajustes dela nao devem mexer duas vezes nos enderecos. */
    private static bool $pausado = false;

    public static function pausar(\Closure $acao): mixed
    {
        $anterior = self::$pausado;
        self::$pausado = true;

        try {
            return $acao();
        } finally {
            self::$pausado = $anterior;
        }
    }

    public function aoMovimentarMaterial(MaterialStockMovement $m): void
    {
        $quantidade = (float) $m->quantity;
        $delta = match ((string) $m->type) {
            MaterialStockMovement::TYPE_SAIDA => -abs($quantidade),
            MaterialStockMovement::TYPE_ENTRADA => abs($quantidade),
            default => $quantidade, // ajuste ja vem com sinal (diferenca)
        };

        $this->aplicar(StockPlacement::MATERIAL, (string) $m->material_id, $delta);
    }

    public function aoMovimentarPeca(StockMovement $m): void
    {
        $tipo = (string) $m->movement_type;
        $entrada = str_starts_with($tipo, 'entry_') || $tipo === 'transfer_in';

        $this->aplicar(StockPlacement::PART, (string) $m->part_id, $entrada ? abs((float) $m->quantity) : -abs((float) $m->quantity));
    }

    /** Aplica a variacao nos enderecos do item (consumo tira dos maiores primeiro; entrada soma no maior). */
    public function aplicar(string $tipo, string $itemId, float $delta): void
    {
        if (self::$pausado || abs($delta) < 0.0005) {
            return;
        }

        DB::transaction(function () use ($tipo, $itemId, $delta) {
            $enderecos = StockPlacement::query()->where('item_type', $tipo)->where('item_id', $itemId)->orderByDesc('quantity')->lockForUpdate()->get();
            if ($enderecos->isEmpty()) {
                return;
            }

            if ($delta > 0) {
                $enderecos->first()->update(['quantity' => (float) $enderecos->first()->quantity + $delta]);

                return;
            }

            $restante = -$delta;
            foreach ($enderecos as $e) {
                if ($restante <= 0) {
                    break;
                }
                $tira = min((float) $e->quantity, $restante);
                if ($tira > 0) {
                    $e->update(['quantity' => (float) $e->quantity - $tira]);
                    $restante -= $tira;
                }
            }
        });
    }
}
