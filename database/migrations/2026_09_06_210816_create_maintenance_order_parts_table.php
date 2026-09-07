<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espelho de maintenance_order_materials, mas para o catalogo Part/
 * Warehouse (Almoxarifado) em vez de Material/InternalUnit -- os dois
 * sistemas de estoque coexistem de proposito (ver
 * project_stock_systems_duplication_material_vs_part na memoria), esta
 * tabela e' a ponte que faltava entre Part e Ordem de Servico.
 *
 * part_id sem nullOnDelete: ao contrario de material_id em
 * maintenance_order_materials (nullable, historico legado sem catalogo),
 * toda linha aqui nasce depois do catalogo de Partes existir -- restrict
 * evita apagar uma Part que tem consumo registrado sem querer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_order_parts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('maintenance_order_id');
            $table->foreignId('part_id')->constrained('parts')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 4)->default(0);
            $table->foreignId('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('maintenance_order_id')->references('id')->on('maintenance_orders')->onDelete('cascade');
            $table->index(['maintenance_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_order_parts');
    }
};
