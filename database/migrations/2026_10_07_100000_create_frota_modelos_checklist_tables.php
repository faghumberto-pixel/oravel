<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frota_modelos_checklist', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('tipo', 20)->default('rapido');          // rapido | completo
            $table->string('tipo_veiculo', 20)->nullable();         // leve | pesado | null = todos
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'ativo']);
        });

        // Item do modelo: sem tenant_id -- o isolamento vem do modelo (pai).
        Schema::create('frota_itens_modelo_checklist', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('modelo_id')->constrained('frota_modelos_checklist')->cascadeOnDelete();
            $table->string('descricao');
            $table->string('categoria', 30)->default('outros');     // pneus, fluidos, luzes, freios, documentos, seguranca, carroceria, outros
            $table->string('gravidade', 20)->default('atencao');    // critica | atencao | informativa
            $table->string('tipo_resposta', 20)->default('ok_problema'); // ok_problema | numero | texto
            $table->string('unidade', 20)->nullable();              // mm, V, bar...
            $table->boolean('exige_foto_se_problema')->default(false);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
            $table->index(['modelo_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_itens_modelo_checklist');
        Schema::dropIfExists('frota_modelos_checklist');
    }
};
