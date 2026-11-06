<?php

namespace App\Dashboards\Blocks\MateriaisPecas;

use App\Dashboards\Payload;
use App\Models\PurchaseOrder;
use Illuminate\Support\Carbon;

class EntregasPrevistas extends MateriaisBlock
{
    public static function key(): string
    {
        return 'materiais.entregas_previstas';
    }

    public static function title(): string
    {
        return 'Entregas previstas';
    }

    public static function subtitle(): string
    {
        return 'Pedidos de compra em aberto, em ordem de data prevista';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_purchase_orders';
    }

    public static function sourceModel(): string
    {
        return PurchaseOrder::class;
    }

    public function data(array $config): array
    {
        $hoje = Carbon::today();
        $pedidos = PurchaseOrder::query()->with('supplier:id,name')->whereIn('status', $this->situacoesEmAberto())->whereNotNull('expected_delivery_date')
            ->orderBy('expected_delivery_date')->limit(8)->get();

        return Payload::timeline($pedidos->map(function ($p) use ($hoje) {
            $data = Carbon::parse($p->expected_delivery_date);
            $atrasado = $data->lt($hoje);

            return [
                $data->format('d/m'), ($p->supplier?->name ?? 'Fornecedor').' — '.$this->brl((float) $p->total_value),
                $atrasado ? 'atrasado há '.(int) $data->diffInDays($hoje).' dias' : ($data->isSameDay($hoje) ? 'chega hoje' : 'chega em '.(int) $hoje->diffInDays($data).' dias'),
                $atrasado ? 'crit' : ($data->diffInDays($hoje) <= 3 ? 'warn' : 'a'), $atrasado ? 'atrasado' : null,
            ];
        })->all(), 'Nenhuma entrega prevista.');
    }
}
