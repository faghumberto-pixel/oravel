<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Base da API do CRM (Fase 5):
     * - personal_access_tokens.tokenable_id era bigint (morphs) mas todo User tem id uuid: createToken()
     *   nunca funcionou. A tabela nunca foi usada (vazia em PROD); converte para uuid e recusa rodar se tiver linhas.
     * - crm_leads.previsao_fechamento (o "prazo" do lead).
     * - crm_lead_interactions.direcao / transcricao (mensagens de WhatsApp registradas pela extensão).
     */
    public function up(): void
    {
        if (DB::table('personal_access_tokens')->exists()) {
            throw new RuntimeException('personal_access_tokens tem linhas: converta tokenable_id para uuid manualmente antes.');
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropMorphs('tokenable');
        });
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->uuidMorphs('tokenable');
        });

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->date('previsao_fechamento')->nullable()->after('estimated_value');
        });

        Schema::table('crm_lead_interactions', function (Blueprint $table) {
            $table->string('direcao', 10)->nullable()->after('channel'); // entrada | saida
            $table->text('transcricao')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('crm_lead_interactions', function (Blueprint $table) {
            $table->dropColumn(['direcao', 'transcricao']);
        });

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropColumn('previsao_fechamento');
        });

        DB::table('personal_access_tokens')->delete();

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropMorphs('tokenable');
        });
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->morphs('tokenable');
        });
    }
};
