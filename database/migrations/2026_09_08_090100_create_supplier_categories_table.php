<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categoria de fornecimento (pecas, combustivel, servicos terceirizados
 * etc.) -- mesmo padrao de MaterialCategory/PartCategory, com Resource
 * proprio e HasSaaSMetadata. Many-to-many: um fornecedor pode atender
 * mais de uma categoria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('supplier_category_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('supplier_category_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['supplier_category_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_category_supplier');
        Schema::dropIfExists('supplier_categories');
    }
};
