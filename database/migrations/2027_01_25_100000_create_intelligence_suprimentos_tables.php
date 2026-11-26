<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo Suprimentos do Intelligence: planilha PRÓPRIA (inventário, compras, movimentações), separada dos snapshots gerais
     * (misturar quebraria o painel, que lê o snapshot mais recente da empresa). Uma "carga" = uma fotografia de suprimentos.
     */
    public function up(): void
    {
        Schema::create('intelligence_suprimentos_cargas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origem', 30)->default('arquivo');
            $table->string('arquivo')->nullable();
            $table->date('data_referencia');
            $table->unsignedSmallInteger('periodo_dias')->default(90);
            $table->string('hash', 64);
            $table->unsignedInteger('total_itens')->default(0);
            $table->unsignedInteger('total_compras')->default(0);
            $table->unsignedInteger('total_movimentos')->default(0);
            $table->json('indicadores');                 // chave => {valor, unidade, ruim, bom, titulo, nota}
            $table->json('diagnostico')->nullable();     // {pontuacao, faixa, detalhes}
            $table->json('listas')->nullable();          // abaixo do mínimo, parados, fornecedores, destinos, pedidos em atraso
            $table->json('avisos')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'data_referencia']);
            $table->unique(['tenant_id', 'hash']);
        });

        Schema::create('intelligence_suprimentos_itens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('carga_id')->constrained('intelligence_suprimentos_cargas')->cascadeOnDelete();
            $table->string('codigo', 80);
            $table->string('descricao', 160);
            $table->string('categoria', 80)->nullable();
            $table->string('unidade', 20)->nullable();
            $table->decimal('quantidade', 14, 3);
            $table->decimal('estoque_minimo', 14, 3)->nullable();
            $table->decimal('custo_unitario', 14, 4);
            $table->date('ultima_entrada')->nullable();
            $table->date('ultima_saida')->nullable();
            $table->string('local', 80)->nullable();
            $table->unique(['carga_id', 'codigo']);
            $table->index(['tenant_id', 'codigo']);
        });

        Schema::create('intelligence_suprimentos_compras', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('carga_id')->constrained('intelligence_suprimentos_cargas')->cascadeOnDelete();
            $table->string('pedido', 80)->nullable();
            $table->date('data_pedido');
            $table->string('codigo_item', 80);
            $table->string('fornecedor', 160);
            $table->decimal('quantidade', 14, 3);
            $table->decimal('valor_total', 14, 2);
            $table->date('prazo_prometido');
            $table->date('data_chegada')->nullable();
            $table->boolean('emergencial')->default(false);
            $table->index(['carga_id', 'fornecedor']);
        });

        Schema::create('intelligence_suprimentos_movimentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('carga_id')->constrained('intelligence_suprimentos_cargas')->cascadeOnDelete();
            $table->date('data');
            $table->string('codigo_item', 80);
            $table->string('tipo', 10);                  // entrada | saida
            $table->decimal('quantidade', 14, 3);
            $table->string('destino', 120)->nullable();
            $table->string('documento', 80)->nullable();
            $table->index(['carga_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_suprimentos_movimentos');
        Schema::dropIfExists('intelligence_suprimentos_compras');
        Schema::dropIfExists('intelligence_suprimentos_itens');
        Schema::dropIfExists('intelligence_suprimentos_cargas');
    }
};
