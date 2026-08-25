<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Item de linha da proposta -- equipamento (asset_category_id, o
     * patrimônio exato só é escolhido depois, na SolicitacaoLocacao) ou
     * serviço (texto livre em description, sem catálogo -- mesmo padrão já
     * usado em QuoteItem::TYPE_SERVICO). start_date/end_date e item_terms
     * são o prazo e a exigência PRÓPRIA de cada item, pedido explícito do
     * usuário -- distintos de valid_until/terms da proposta como um todo.
     */
    public function up(): void
    {
        Schema::create('proposta_comercial_itens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('proposta_comercial_id')->constrained('proposta_comerciais')->cascadeOnDelete();
            $table->foreignUuid('asset_category_id')->nullable()->constrained('asset_categories')->nullOnDelete();

            $table->string('type')->default('equipamento');
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->string('unit_period')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('item_terms')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposta_comercial_itens');
    }
};
