<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pmoc_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pmoc_id')->constrained('pmocs')->cascadeOnDelete();
            $table->foreignUuid('maintenance_order_id')->nullable()->constrained('maintenance_orders')->nullOnDelete();
            $table->date('data_verificacao');
            $table->time('hora_verificacao');
            $table->string('responsavel_verificacao');
            $table->decimal('temperatura_medida', 5, 2)->nullable();
            $table->decimal('umidade_medida', 5, 2)->nullable();
            $table->text('filtros_verificados')->nullable();
            $table->boolean('filtros_trocados')->default(false);
            $table->text('limpeza_realizada')->nullable();
            $table->boolean('limpeza_ok')->default(false);
            $table->text('inspecao_resultado')->nullable();
            $table->boolean('inspecao_ok')->default(false);
            $table->text('anomalias_detectadas')->nullable();
            $table->text('manutencoes_recomendadas')->nullable();
            $table->text('observacoes')->nullable();
            $table->enum('status', ['ok', 'com_desvio', 'falha'])->default('ok');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pmoc_checks');
    }
};
