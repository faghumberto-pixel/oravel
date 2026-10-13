<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frota_percursos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            // Trajeto entre dois locais (A ↔ B): vale nos dois sentidos (empresa → cliente e cliente → empresa).
            $table->string('local_a_tipo', 10)->default('empresa');   // empresa | cliente | outro
            $table->foreignUuid('local_a_cliente_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('local_a_texto')->nullable();
            $table->string('local_b_tipo', 10)->default('cliente');
            $table->foreignUuid('local_b_cliente_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('local_b_texto')->nullable();
            $table->decimal('km_previsto', 10, 1)->nullable();         // só para comparar com o km real das viagens
            $table->boolean('ativo')->default(true);
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_percursos');
    }
};
