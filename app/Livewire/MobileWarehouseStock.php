<?php

namespace App\Livewire;

use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Meu Estoque" -- consulta do saldo do próprio Almoxarifado Volante pelo
 * técnico no celular. Read-only de propósito: a baixa de peça acontece
 * via StockTransferService::consumePartInWorkOrder() ao aplicar a peça na
 * OS (Modo Campo), não editando o saldo diretamente aqui.
 */
#[Layout('layouts.checklist-mobile')]
class MobileWarehouseStock extends Component
{
    public ?Warehouse $warehouse = null;

    public string $search = '';

    public function mount(): void
    {
        $this->warehouse = Warehouse::mobileForUser(Auth::id())->first();
    }

    public function getStocksProperty()
    {
        if (! $this->warehouse) {
            return collect();
        }

        return WarehouseStock::where('warehouse_id', $this->warehouse->id)
            ->with('part')
            ->get()
            ->filter(fn (WarehouseStock $stock) => $stock->part && (
                blank($this->search)
                || str_contains(mb_strtolower($stock->part->name), mb_strtolower($this->search))
                || str_contains(mb_strtolower($stock->part->sku), mb_strtolower($this->search))
            ))
            ->sortBy(fn (WarehouseStock $stock) => $stock->part->name);
    }

    public function render()
    {
        return view('livewire.mobile-warehouse-stock', [
            'stocks' => $this->stocks,
        ]);
    }
}
