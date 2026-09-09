<?php

namespace Database\Seeders;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InternalUnit;
use App\Models\MaintenanceOrder;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Models\MaterialRequestQuotation;
use App\Models\PartsRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Popula o ciclo completo de Compras (Fornecedores, Requisicoes, Cotacoes,
 * Ordens de Compra, Recebimentos, Solicitacoes de Pecas) pro tenant Demo
 * Empilhadeiras -- unico com Materials/Assets ja cadastrados. Roda o fluxo
 * real via GoodsReceiptItem (dispara GoodsReceiptItemObserver: baixa de
 * estoque + recalculo de status), nao seta quantity_received/status na mao.
 */
class DemoComprasSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(fn () => $this->seed());
    }

    private function seed(): void
    {
        $tenant = Tenant::where('name', 'Demo Empilhadeiras')->firstOrFail();
        $tenantId = $tenant->id;
        // $tenant->users() usa a pivot tenant_user, que nao existe (ver
        // memoria project_tenant_user_pivot_table_missing) -- busca direto.
        $admin = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->firstOrFail();

        $unit = InternalUnit::create([
            'tenant_id' => $tenantId,
            'name' => 'Filial Matriz',
            'code' => 'MTZ',
            'city' => 'Campinas',
            'state' => 'SP',
            'is_active' => true,
            'type' => 'filial',
        ]);

        $materials = Material::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('sku')->get();

        $suppliers = collect([
            ['name' => 'Ferramentas Industriais Campinas Ltda', 'document' => '12.345.678/0001-90', 'email' => 'vendas@ficampinas.com.br', 'phone' => '(19) 3234-5566'],
            ['name' => 'Peças & Componentes Hidráulicos SA', 'document' => '23.456.789/0001-01', 'email' => 'comercial@pchidraulicos.com.br', 'phone' => '(11) 4455-6677'],
            ['name' => 'Distribuidora Mecânica Sul Ltda', 'document' => '34.567.890/0001-12', 'email' => 'contato@mecanicasul.com.br', 'phone' => '(51) 3322-1188'],
            ['name' => 'AutoPeças Empilhadeiras Brasil ME', 'document' => '45.678.901/0001-23', 'email' => 'pedidos@autopecasempilhadeiras.com.br', 'phone' => '(19) 3877-4400'],
        ])->map(fn (array $data) => Supplier::create($data + [
            'tenant_id' => $tenantId,
            'compliance_ceis_cnep' => true,
            'lista_trabalho_escravo' => true,
            'termo_lgpd' => true,
        ]));

        [$fornecedorA, $fornecedorB, $fornecedorC, $fornecedorD] = $suppliers->all();

        // MR-A: aprovada -> cotada -> OC totalmente recebida
        $mrA = MaterialRequest::create([
            'tenant_id' => $tenantId,
            'user_id' => $admin->id,
            'origin' => MaterialRequest::ORIGIN_MANUAL,
            'priority' => MaterialRequest::PRIORITY_NORMAL,
            'status' => MaterialRequest::STATUS_APROVADA,
            'requested_at' => now()->subDays(12),
            'approved_by_user_id' => $admin->id,
            'approved_at' => now()->subDays(10),
            'requested_for_location_id' => $unit->id,
            'notes' => 'Reposição trimestral de itens de desgaste rápido.',
        ]);
        $itemsA = $materials->slice(0, 3)->values();
        foreach ($itemsA as $m) {
            MaterialRequestItem::create([
                'material_request_id' => $mrA->id,
                'material_id' => $m->id,
                'quantity' => 10,
                'cost_price' => $m->unit_cost,
            ]);
        }
        $quotA = MaterialRequestQuotation::create([
            'tenant_id' => $tenantId,
            'material_request_id' => $mrA->id,
            'supplier_id' => $fornecedorA->id,
            'total_value' => $itemsA->sum(fn ($m) => $m->unit_cost * 10),
            'delivery_days' => 5,
            'payment_terms' => '28 dias',
            'is_selected' => true,
        ]);
        $poA = PurchaseOrder::create([
            'tenant_id' => $tenantId,
            'material_request_id' => $mrA->id,
            'material_request_quotation_id' => $quotA->id,
            'supplier_id' => $fornecedorA->id,
            'status' => PurchaseOrder::STATUS_ABERTA,
            'total_value' => $quotA->total_value,
            'expected_delivery_date' => now()->subDays(5),
            'created_by_user_id' => $admin->id,
        ]);
        $poAItems = $itemsA->map(fn ($m) => PurchaseOrderItem::create([
            'tenant_id' => $tenantId,
            'purchase_order_id' => $poA->id,
            'material_id' => $m->id,
            'quantity' => 10,
            'unit_price' => $m->unit_cost,
        ]));
        $grA = GoodsReceipt::create([
            'tenant_id' => $tenantId,
            'purchase_order_id' => $poA->id,
            'internal_unit_id' => $unit->id,
            'received_by_user_id' => $admin->id,
            'received_at' => now()->subDays(3),
            'invoice_number' => 'NF-000441',
        ]);
        foreach ($poAItems as $item) {
            GoodsReceiptItem::create([
                'tenant_id' => $tenantId,
                'goods_receipt_id' => $grA->id,
                'purchase_order_item_id' => $item->id,
                'quantity_received' => 10,
            ]);
        }
        $mrA->update(['status' => MaterialRequest::STATUS_ENTREGUE, 'delivered_at' => now()->subDays(3)]);

        // MR-B: aprovada -> cotada -> OC parcialmente recebida
        $mrB = MaterialRequest::create([
            'tenant_id' => $tenantId,
            'user_id' => $admin->id,
            'origin' => MaterialRequest::ORIGIN_REPOSICAO_ESTOQUE,
            'priority' => MaterialRequest::PRIORITY_URGENTE,
            'status' => MaterialRequest::STATUS_APROVADA,
            'requested_at' => now()->subDays(6),
            'approved_by_user_id' => $admin->id,
            'approved_at' => now()->subDays(5),
            'requested_for_location_id' => $unit->id,
            'notes' => 'Gerada automaticamente por estoque abaixo do mínimo.',
        ]);
        $itemsB = $materials->slice(3, 2)->values();
        foreach ($itemsB as $m) {
            MaterialRequestItem::create([
                'material_request_id' => $mrB->id,
                'material_id' => $m->id,
                'quantity' => 20,
                'cost_price' => $m->unit_cost,
            ]);
        }
        $quotB = MaterialRequestQuotation::create([
            'tenant_id' => $tenantId,
            'material_request_id' => $mrB->id,
            'supplier_id' => $fornecedorB->id,
            'total_value' => $itemsB->sum(fn ($m) => $m->unit_cost * 20),
            'delivery_days' => 10,
            'payment_terms' => '30/60 dias',
            'is_selected' => true,
        ]);
        $poB = PurchaseOrder::create([
            'tenant_id' => $tenantId,
            'material_request_id' => $mrB->id,
            'material_request_quotation_id' => $quotB->id,
            'supplier_id' => $fornecedorB->id,
            'status' => PurchaseOrder::STATUS_ABERTA,
            'total_value' => $quotB->total_value,
            'expected_delivery_date' => now()->addDays(2),
            'created_by_user_id' => $admin->id,
        ]);
        $poBItems = $itemsB->map(fn ($m) => PurchaseOrderItem::create([
            'tenant_id' => $tenantId,
            'purchase_order_id' => $poB->id,
            'material_id' => $m->id,
            'quantity' => 20,
            'unit_price' => $m->unit_cost,
        ]));
        $grB = GoodsReceipt::create([
            'tenant_id' => $tenantId,
            'purchase_order_id' => $poB->id,
            'internal_unit_id' => $unit->id,
            'received_by_user_id' => $admin->id,
            'received_at' => now()->subDay(),
            'invoice_number' => 'NF-000487',
            'notes' => 'Entrega parcial -- restante previsto pro próximo lote.',
        ]);
        foreach ($poBItems as $item) {
            GoodsReceiptItem::create([
                'tenant_id' => $tenantId,
                'goods_receipt_id' => $grB->id,
                'purchase_order_item_id' => $item->id,
                'quantity_received' => 8, // menos que os 20 pedidos -> parcialmente_recebida
            ]);
        }
        $mrB->update(['status' => MaterialRequest::STATUS_A_CAMINHO]);

        // MR-C: aguardando aprovação (sem cotação/OC ainda)
        $mrC = MaterialRequest::create([
            'tenant_id' => $tenantId,
            'user_id' => $admin->id,
            'origin' => MaterialRequest::ORIGIN_MANUAL,
            'priority' => MaterialRequest::PRIORITY_NORMAL,
            'status' => MaterialRequest::STATUS_AGUARDANDO_APROVACAO,
            'requested_at' => now()->subDay(),
            'requested_for_location_id' => $unit->id,
            'notes' => 'Aguardando aprovação do gestor de suprimentos.',
        ]);
        foreach ($materials->slice(5, 2) as $m) {
            MaterialRequestItem::create([
                'material_request_id' => $mrC->id,
                'material_id' => $m->id,
                'quantity' => 15,
                'cost_price' => $m->unit_cost,
            ]);
        }

        // MR-D: rascunho
        $mrD = MaterialRequest::create([
            'tenant_id' => $tenantId,
            'user_id' => $admin->id,
            'origin' => MaterialRequest::ORIGIN_MANUAL,
            'priority' => MaterialRequest::PRIORITY_NORMAL,
            'status' => MaterialRequest::STATUS_RASCUNHO,
            'requested_at' => now(),
            'requested_for_location_id' => $unit->id,
            'notes' => 'Rascunho -- ainda selecionando itens.',
        ]);
        MaterialRequestItem::create([
            'material_request_id' => $mrD->id,
            'material_id' => $materials->get(7)->id,
            'quantity' => 5,
            'cost_price' => $materials->get(7)->unit_cost,
        ]);

        // OC avulsa, sem requisição formal, ainda aberta (sem recebimento)
        $itemsAvulsa = $materials->slice(9, 2)->values();
        $poAvulsa = PurchaseOrder::create([
            'tenant_id' => $tenantId,
            'supplier_id' => $fornecedorC->id,
            'status' => PurchaseOrder::STATUS_ABERTA,
            'total_value' => $itemsAvulsa->sum(fn ($m) => $m->unit_cost * 12),
            'expected_delivery_date' => now()->addDays(7),
            'created_by_user_id' => $admin->id,
        ]);
        foreach ($itemsAvulsa as $m) {
            PurchaseOrderItem::create([
                'tenant_id' => $tenantId,
                'purchase_order_id' => $poAvulsa->id,
                'material_id' => $m->id,
                'quantity' => 12,
                'unit_price' => $m->unit_cost,
            ]);
        }

        // Ordens de manutenção mínimas só pra ancorar Solicitações de Peças
        $assets = $tenant->assets()->get();
        $mo1 = MaintenanceOrder::create([
            'tenant_id' => $tenantId,
            'asset_id' => $assets->get(0)->id,
            'description' => 'Troca de peças de desgaste após inspeção periódica.',
            'created_by' => $admin->id,
        ]);
        $mo2 = MaintenanceOrder::create([
            'tenant_id' => $tenantId,
            'asset_id' => $assets->get(1)->id,
            'description' => 'Reparo corretivo -- peça avariada em campo.',
            'created_by' => $admin->id,
        ]);

        PartsRequest::create([
            'tenant_id' => $tenantId,
            'maintenance_order_id' => $mo1->id,
            'material_id' => $materials->get(11)->id,
            'quantity' => 2,
            'status' => 'pendente',
            'cost_at_time' => $materials->get(11)->unit_cost,
        ]);
        PartsRequest::create([
            'tenant_id' => $tenantId,
            'maintenance_order_id' => $mo1->id,
            'material_id' => $materials->get(12)->id,
            'quantity' => 1,
            'status' => 'pedida',
            'cost_at_time' => $materials->get(12)->unit_cost,
        ]);
        PartsRequest::create([
            'tenant_id' => $tenantId,
            'maintenance_order_id' => $mo2->id,
            'material_id' => $materials->get(13)->id,
            'quantity' => 3,
            'status' => 'entregue',
            'cost_at_time' => $materials->get(13)->unit_cost,
        ]);
        PartsRequest::create([
            'tenant_id' => $tenantId,
            'maintenance_order_id' => $mo2->id,
            'material_id' => $materials->get(14)->id,
            'quantity' => 1,
            'status' => 'pendente',
            'cost_at_time' => $materials->get(14)->unit_cost,
        ]);

        $this->command?->info('Demo Compras: '
            . $suppliers->count() . ' fornecedores, 4 requisições, 3 ordens de compra, 2 recebimentos, 4 solicitações de peças.');
    }
}
