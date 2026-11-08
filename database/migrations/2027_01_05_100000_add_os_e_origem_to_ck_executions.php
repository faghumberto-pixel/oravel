<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O checklist passa a fazer parte do atendimento da OS: a execucao guarda em qual OS foi feita (os_id, diferente de ordem_servico_id, que e a
 * OS corretiva aberta por item critico reprovado), de onde veio o registro (tela ou papel lido pela IA) e as fotos das folhas em papel.
 * Aditiva: so colunas nulas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ck_executions', function (Blueprint $table) {
            $table->foreignUuid('os_id')->nullable()->constrained('maintenance_orders')->nullOnDelete();
            $table->string('registro_origem', 20)->nullable();   // nulo = tela (celular/desktop); papel_ia = foto da folha impressa lida pela IA e conferida
            $table->jsonb('folhas')->nullable();                 // caminhos das fotos das folhas em papel (disco privado)
            $table->index(['tenant_id', 'os_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ck_executions', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'os_id']);
            $table->dropConstrainedForeignId('os_id');
            $table->dropColumn(['registro_origem', 'folhas']);
        });
    }
};
