<?php

namespace Tests\Feature\Modulos\Detalhado;

use App\Filament\Resources\GoodsReceiptResource;
use App\Models\GoodsReceipt;
use App\Models\InternalUnit;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

/** Recebimentos de compra (Materiais e Pecas). */
class GoodsReceiptTest extends ModuloTestCase
{
    protected function modelo(): string
    {
        return GoodsReceipt::class;
    }

    protected function resource(): string
    {
        return GoodsReceiptResource::class;
    }

    private function pedido(Tenant $tenant): PurchaseOrder
    {
        $dono = $tenant->is($this->tenantA) ? $this->a['admin'] : $this->b['admin'];
        $fornecedor = $this->criar(Supplier::class, ['tenant_id' => $tenant->id, 'name' => 'Fornecedor '.uniqid()]);

        return $this->criar(PurchaseOrder::class, [
            'tenant_id' => $tenant->id, 'supplier_id' => $fornecedor->id, 'created_by_user_id' => $dono->id,
        ]);
    }

    private function unidade(Tenant $tenant): InternalUnit
    {
        return $this->criar(InternalUnit::class, ['tenant_id' => $tenant->id, 'name' => 'Almoxarifado '.uniqid()]);
    }

    protected function novoRegistro(Tenant $tenant): Model
    {
        $dono = $tenant->is($this->tenantA) ? $this->a['admin'] : $this->b['admin'];

        return $this->criar(GoodsReceipt::class, [
            'tenant_id' => $tenant->id, 'purchase_order_id' => $this->pedido($tenant)->id,
            'internal_unit_id' => $this->unidade($tenant)->id, 'received_by_user_id' => $dono->id,
            'received_at' => now(), 'invoice_number' => 'NF-'.random_int(100, 999),
        ]);
    }

    protected function dadosDoFormulario(): array
    {
        return [
            'purchase_order_id' => $this->pedido($this->tenantA)->id,
            'internal_unit_id' => $this->unidade($this->tenantA)->id,
            'received_by_user_id' => $this->a['admin']->id,
            'received_at' => now()->toDateTimeString(),
            'invoice_number' => 'NF-12345',
            'notes' => 'Entrega completa',
        ];
    }

    protected function camposObrigatorios(): array
    {
        return ['purchase_order_id', 'internal_unit_id'];
    }
}
