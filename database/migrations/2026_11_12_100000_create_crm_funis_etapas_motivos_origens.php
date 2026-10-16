<?php

use App\Services\CrmFunilPadrao;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Funis e etapas configuráveis do CRM. As 5 etapas fixas antigas viram o
     * funil padrão de cada tenant; crm_leads.stage NÃO é tocado (segue como
     * espelho do slug da etapa), então o down() só remove o que foi criado.
     */
    public function up(): void
    {
        Schema::create('crm_funis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->boolean('padrao')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });

        // Um funil padrão por tenant.
        DB::statement('CREATE UNIQUE INDEX crm_funis_um_padrao_por_tenant ON crm_funis (tenant_id) WHERE padrao = true');

        Schema::create('crm_etapas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('crm_funil_id')->constrained('crm_funis')->cascadeOnDelete();
            $table->string('nome');
            $table->string('slug');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->unsignedTinyInteger('probabilidade')->default(0); // 0-100
            $table->string('tipo', 10)->default('aberta'); // aberta | ganho | perdido
            $table->string('cor', 20)->nullable();
            $table->timestamps();

            $table->unique(['crm_funil_id', 'slug']);
            $table->index(['tenant_id', 'crm_funil_id', 'ordem']);
        });

        Schema::create('crm_motivos_perda', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('slug');
            $table->boolean('exige_detalhe')->default(false);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('crm_origens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('slug');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->foreignUuid('etapa_id')->nullable()->after('stage')->constrained('crm_etapas')->restrictOnDelete();
            $table->index(['tenant_id', 'etapa_id']);
        });

        // Funil padrão + motivos + origens em todo tenant que já existe, e
        // etapa_id de cada lead pelo slug do stage atual.
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            CrmFunilPadrao::garantir((string) $tenantId);
        }
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'etapa_id']);
            $table->dropConstrainedForeignId('etapa_id');
        });

        Schema::dropIfExists('crm_origens');
        Schema::dropIfExists('crm_motivos_perda');
        Schema::dropIfExists('crm_etapas');
        DB::statement('DROP INDEX IF EXISTS crm_funis_um_padrao_por_tenant');
        Schema::dropIfExists('crm_funis');
    }
};
