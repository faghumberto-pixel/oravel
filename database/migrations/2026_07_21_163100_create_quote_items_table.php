<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Item de linha do orçamento -- peça (Almoxarifado, material_id
     * opcional quando vem de um item de estoque real) ou serviço (mão de
     * obra/montagem). Mesmo formato de MaintenanceOrderMaterial
     * (quantity + unit_price), pra ficar consistente com o resto do
     * sistema.
     */
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('material_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type')->default('peca');
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
