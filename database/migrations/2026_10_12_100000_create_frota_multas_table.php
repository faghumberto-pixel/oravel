<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Multas de trânsito da frota: por veículo, com o condutor, os pontos, o valor, o prazo e a situação. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frota_multas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignUuid('motorista_id')->nullable()->constrained('fleet_drivers')->nullOnDelete();   // condutor responsável
            $table->foreignUuid('saida_veiculo_id')->nullable()->constrained('frota_saidas_veiculo')->nullOnDelete(); // saída em que ocorreu (sugestão automática)
            $table->string('numero_auto', 60);
            $table->timestamp('infracao_em');
            $table->string('local')->nullable();
            $table->string('codigo_infracao', 20)->nullable();
            $table->text('descricao');
            $table->string('gravidade', 15);                         // leve | media | grave | gravissima
            $table->unsignedSmallInteger('pontos')->default(0);
            $table->decimal('valor', 12, 2);
            $table->date('vencimento');                              // vencimento do pagamento
            $table->date('prazo_indicacao')->nullable();             // prazo para indicar o condutor
            $table->string('situacao', 20)->default('aberta');       // aberta | condutor_indicado | recorrida | paga | cancelada
            $table->date('condutor_indicado_em')->nullable();
            $table->date('pago_em')->nullable();
            $table->string('quem_paga', 15)->default('empresa');     // empresa | motorista
            $table->text('observacoes')->nullable();
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'numero_auto']);
            $table->index(['tenant_id', 'situacao', 'vencimento']);
            $table->index(['tenant_id', 'motorista_id', 'infracao_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_multas');
    }
};
