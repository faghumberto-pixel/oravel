<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tipos de item agregado (Bandeja de Contenção, Cabo, Mangueira...).
        Schema::create('aggregate_item_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // Intervalo padrão de inspeção/validade; preenche o vencimento do item.
            $table->unsignedInteger('inspection_interval_days')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'name']);
        });

        // Cada unidade física de item agregado, com controle próprio
        // (status, conservação, compra, vencimento), separado de Asset e de
        // Material/Part.
        Schema::create('aggregate_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('aggregate_item_type_id')->constrained('aggregate_item_types')->restrictOnDelete();
            $table->string('code');
            $table->string('description')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('status')->default('disponivel');
            $table->string('condition')->default('bom');
            // Equipamento ao qual está acompanhando agora (null = avulso/estoque).
            $table->foreignUuid('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_value', 12, 2)->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('warranty_until')->nullable();
            $table->date('next_inspection_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'next_inspection_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aggregate_items');
        Schema::dropIfExists('aggregate_item_types');
    }
};
