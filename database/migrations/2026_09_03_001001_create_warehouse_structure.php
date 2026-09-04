<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela de Almoxarifados/Depósitos
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->string('name'); // "Galpão Principal - São Paulo"
            $table->string('code')->nullable()->index(); // "ALM-01"
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('manager_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('manager_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['tenant_id', 'is_active']);
        });

        // Categorias de Peças/Insumos
        Schema::create('part_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'slug']);
        });

        // Catálogo de Peças/Itens
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('part_category_id')->constrained('part_categories')->onDelete('restrict');
            $table->string('sku')->index(); // "FLT-001-OLE"
            $table->string('barcode')->nullable()->index(); // EAN/UPC
            $table->string('name'); // "Filtro de Óleo HF1234"
            $table->text('description')->nullable();
            $table->string('unit_of_measure'); // UN, PC, LT, KG, MT, JG
            $table->decimal('cost_price', 12, 4)->default(0); // Custo médio ponderado
            $table->decimal('minimum_stock', 10, 2)->default(0);
            $table->decimal('maximum_stock', 10, 2)->nullable();
            $table->string('location_shelf')->nullable(); // "Corredor B - Prateleira 3 - Gaveta 12"
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'is_active']);
            $table->index(['barcode', 'tenant_id']);
        });

        // Saldo de Estoque por Almoxarifado
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignId('part_id')->constrained('parts')->onDelete('cascade');
            $table->decimal('current_quantity', 10, 2)->default(0);
            $table->decimal('reserved_quantity', 10, 2)->default(0); // Reservado para OS em aberto
            $table->timestamps();

            $table->unique(['warehouse_id', 'part_id']);
            $table->index(['warehouse_id']);
            $table->index(['part_id']);
        });

        // Histórico de Movimentações (Kardex / Auditoria)
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('part_id')->constrained('parts')->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->string('movement_type'); // entry_purchase, entry_adjustment, entry_return, exit_work_order, exit_adjustment, exit_loss, transfer_out, transfer_in
            $table->decimal('quantity', 10, 2); // Sempre positivo
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('total_cost', 12, 2);
            $table->string('reference_document')->nullable(); // NF, ID de OS, etc
            $table->text('notes')->nullable();
            $table->uuid('created_by'); // Usuário responsável
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->index(['tenant_id', 'created_at']);
            $table->index(['part_id', 'warehouse_id']);
            $table->index(['warehouse_id']);
            $table->index(['movement_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('warehouse_stocks');
        Schema::dropIfExists('parts');
        Schema::dropIfExists('part_categories');
        Schema::dropIfExists('warehouses');
    }
};
