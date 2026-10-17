<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Complementos cobrados pela Oravel dentro da assinatura recorrente do cliente, com fidelidade de 12 meses.
     * tenants.mrr_value continua sendo o TOTAL mensal cobrado; mrr_base guarda a parte sem complementos.
     */
    public function up(): void
    {
        Schema::create('tenant_complementos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('feature', 80);                     // chave do módulo no contrato (ex.: modulo_cobranca_automatica)
            $table->decimal('valor_mensal', 10, 2);
            $table->date('contratado_em');
            $table->unsignedSmallInteger('fidelidade_meses')->default(12);
            $table->date('fidelidade_ate');
            $table->string('status', 15)->default('ativo');    // ativo | encerrando (cancelamento agendado) | encerrado
            $table->date('encerra_em')->nullable();            // quando o cancelamento agendado vale (próximo ciclo)
            $table->timestamp('encerrado_em')->nullable();
            $table->uuid('encerrado_por')->nullable();
            $table->text('motivo_encerramento')->nullable();
            $table->boolean('encerramento_antecipado')->default(false);
            $table->date('aviso_fidelidade_em')->nullable();   // data em que o aviso de fim da fidelidade foi dado
            $table->timestamps();
        });

        // No máximo um complemento ativo (ou em cancelamento) por empresa e módulo.
        DB::statement("CREATE UNIQUE INDEX tenant_complementos_vigente_unique ON tenant_complementos (tenant_id, feature) WHERE status IN ('ativo', 'encerrando')");

        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('mrr_base', 12, 2)->nullable()->after('mrr_value');
            $table->boolean('mrr_pendente_sincronizar')->default(false)->after('mrr_base');
            $table->timestamp('mrr_pendente_desde')->nullable()->after('mrr_pendente_sincronizar');
        });

        DB::table('tenants')->update(['mrr_base' => DB::raw('mrr_value')]);
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn(['mrr_base', 'mrr_pendente_sincronizar', 'mrr_pendente_desde']));
        Schema::dropIfExists('tenant_complementos');
    }
};
