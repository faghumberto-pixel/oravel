<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Padrão UUID para coerência com o sistema
            $table->uuid('tenant_id')->index(); // Multitenant isolado
            $table->uuid('internal_unit_id')->index(); // Estoque vinculado à Filial
            
            $table->string('name');
            $table->string('sku')->nullable(); // Código de identificação/part number
            $table->text('description')->nullable();
            
            $table->decimal('current_stock', 10, 2)->default(0); // Estoque atual
            $table->decimal('min_stock', 10, 2)->default(0);     // Alerta de reposição
            $table->decimal('unit_price', 15, 2);                // Preço de custo da peça
            
            $table->timestamps();
            $table->softDeletes(); // Princípio de não excluir dados (zero exclusão)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};