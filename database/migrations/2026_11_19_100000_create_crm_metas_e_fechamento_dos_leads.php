<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Metas de vendas (Fase 6C) e o fechamento datado dos leads: sem `fechado_em` não dá para saber em que mês
     * uma venda foi ganha. `valor_fechado` e `vendedor_fechamento_id` são o retrato do momento do fechamento.
     */
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->timestamp('fechado_em')->nullable()->after('lost_reason_category');
            $table->decimal('valor_fechado', 15, 2)->nullable()->after('fechado_em');
            $table->foreignUuid('vendedor_fechamento_id')->nullable()->after('valor_fechado')->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'fechado_em']);
        });

        // Leads que já estão em etapa final: melhor estimativa possível (data da última alteração).
        DB::statement("
            UPDATE crm_leads l
            SET fechado_em = l.updated_at,
                valor_fechado = CASE WHEN e.tipo = 'ganho' THEN l.estimated_value END,
                vendedor_fechamento_id = l.assigned_user_id
            FROM crm_etapas e
            WHERE e.id = l.etapa_id AND e.tipo IN ('ganho', 'perdido')
        ");

        Schema::create('crm_metas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete(); // nulo = meta da empresa/equipe
            $table->date('mes'); // sempre o dia 1
            $table->decimal('meta_valor', 15, 2)->nullable();
            $table->unsignedInteger('meta_ganhos')->nullable();
            $table->unsignedInteger('meta_visitas')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'mes']);
        });

        DB::statement('CREATE UNIQUE INDEX crm_metas_equipe_unica ON crm_metas (tenant_id, mes) WHERE user_id IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS crm_metas_equipe_unica');
        Schema::dropIfExists('crm_metas');

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'fechado_em']);
            $table->dropConstrainedForeignId('vendedor_fechamento_id');
            $table->dropColumn(['valor_fechado', 'fechado_em']);
        });
    }
};
