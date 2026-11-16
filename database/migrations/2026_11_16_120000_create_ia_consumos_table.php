<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consumo de tokens da IA, uma linha por chamada a API (painel da Central: por usuario, cliente e
 * aplicativo, em tempo real). Guarda SO numeros e quem/onde: nunca a pergunta nem a resposta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_consumos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index();
            $table->uuid('user_id')->nullable()->index();
            $table->string('area', 30)->index();
            $table->string('etapa', 20)->default('ask');
            $table->string('model', 60);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->decimal('custo_usd', 12, 6)->default(0);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_consumos');
    }
};
