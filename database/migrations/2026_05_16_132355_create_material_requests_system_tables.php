<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. TABELA PRINCIPAL DE REQUISIÇÕES/PEDIDOS DE MATERIAL
        Schema::create('material_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index(); // Multi-tenancy absoluto
            
            // Relacionamentos e Rastreabilidade do Processo
            $table->unsignedBigInteger('user_id'); // Quem solicitou no pátio
            $table->uuid('maintenance_order_id')->nullable(); // Se o pedido nasceu de uma O.S. específica
            $table->string('provider_name')->nullable(); // Nome do fornecedor (para análise de saúde/cadastro)
            
            // Controle de Tempo e SLA (Crucial para rastrear tempo até a chegada)
            $table->string('status')->default('pendente'); // pendente, cotado, aprovado, a_caminho, entregue, recusado
            $table->timestamp('requested_at')->useCurrent(); // Data exata do pedido
            $table->timestamp('delivered_at')->nullable(); // Data real de chegada (calcula o Lead Time)
            
            $table->text('notes')->nullable(); // Observações gerais
            $table->timestamps();

            // Chaves Estrangeiras de Segurança
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('maintenance_order_id')->references('id')->on('maintenance_orders')->onDelete('set null');
        });

        // 2. TABELA DE ITENS DO PEDIDO (MARCA, QUANTIDADE, QUALIDADE)
        Schema::create('material_request_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('material_request_id'); // Elo com o pedido pai
            $table->uuid('material_id'); // Link com o cadastro de Material que você me mostrou (SKU, NCM)
            
            // Métricas de Auditoria Exigidas pelo Processo
            $table->integer('quantity'); // Quantidade pedida
            $table->string('brand')->nullable(); // Marca da peça comprada (para avaliar durabilidade)
            $table->decimal('cost_price', 15, 2)->nullable(); // Preço real praticado pelo fornecedor
            
            // Controle de Qualidade ISO 9001
            $table->integer('quality_rating')->nullable(); // Nota de 1 a 5 para a qualidade da peça recebida
            $table->text('quality_notes')->nullable(); // Relato técnico de falha ou conformidade da peça
            
            $table->timestamps();

            // Chaves Estrangeiras
            $table->foreign('material_request_id')->references('id')->on('material_requests')->onDelete('cascade');
            $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_request_items');
        Schema::dropIfExists('material_requests');
    }
};