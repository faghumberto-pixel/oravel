<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Entrada de Itens Agregados (compra): data, fornecedor, preço, quantidade, total.
        Schema::create('aggregate_item_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('aggregate_item_type_id')->constrained('aggregate_item_types')->restrictOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('entry_date');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedInteger('quantity');
            $table->decimal('total', 14, 2)->default(0);
            $table->string('invoice_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'entry_date']);
        });

        // Saída de Itens Agregados: vai para um equipamento, com motivo e (opcional) OS.
        Schema::create('aggregate_item_exits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('aggregate_item_type_id')->constrained('aggregate_item_types')->restrictOnDelete();
            // Unidade específica (opcional) quando o item é controlado unidade a unidade.
            $table->foreignUuid('aggregate_item_id')->nullable()->constrained('aggregate_items')->nullOnDelete();
            $table->foreignUuid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignUuid('maintenance_order_id')->nullable()->constrained('maintenance_orders')->nullOnDelete();
            $table->date('exit_date');
            $table->unsignedInteger('quantity');
            $table->string('reason');
            $table->boolean('returned')->default(false);
            $table->date('returned_at')->nullable();
            $table->string('returned_condition')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'exit_date']);
            $table->index(['tenant_id', 'returned']);
        });

        // Entrada de EPI (compra): alimenta o estoque do Material EPI na filial.
        Schema::create('epi_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignUuid('internal_unit_id')->constrained('internal_units')->restrictOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('entry_date');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedInteger('quantity');
            $table->decimal('total', 14, 2)->default(0);
            $table->string('invoice_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epi_entries');
        Schema::dropIfExists('aggregate_item_exits');
        Schema::dropIfExists('aggregate_item_entries');
    }
};
