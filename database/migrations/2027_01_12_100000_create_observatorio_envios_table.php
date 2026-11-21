<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbox da integracao com o Observatorio. O evento nasce na mesma transacao da atividade do CRM e e'
 * enviado em segundo plano. Depois de entregue, o payload (respostas e observacao) e' APAGADO daqui:
 * dados de pesquisa pertencem ao Observatorio, nao a Central.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observatorio_envios', function (Blueprint $t) {
            $t->uuid('id')->primary();                 // = event_id do contrato
            $t->string('event_type', 60)->default('entrevista.registrada');
            $t->uuid('entrevista_ref');
            $t->foreignUuid('prospect_id')->nullable()->constrained('prospects')->nullOnDelete();
            $t->unsignedBigInteger('pesquisa_id');
            $t->string('pesquisa_titulo', 200)->nullable();
            $t->json('payload')->nullable();           // envelope completo; nulo apos entrega
            $t->string('status', 30)->default('pendente'); // pendente | enviado | falhou | rejeitado | versao_desatualizada
            $t->unsignedSmallInteger('tentativas')->default(0);
            $t->timestamp('proxima_tentativa_em')->nullable();
            $t->text('ultimo_erro')->nullable();
            $t->timestamp('enviado_em')->nullable();
            $t->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete(); // users.id e' UUID na Central
            $t->timestamps();
            $t->index(['status', 'proxima_tentativa_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observatorio_envios');
    }
};
