<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos personalizados por tenant (leads e clientes). Os valores ficam em
     * `custom` (jsonb) de cada registro, chaveados pela `chave` do campo.
     */
    public function up(): void
    {
        Schema::create('crm_campos_custom', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('entidade', 10); // lead | cliente
            $table->string('nome');
            $table->string('chave');
            $table->string('tipo', 20);
            $table->jsonb('opcoes')->nullable();
            $table->boolean('obrigatorio')->default(false);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'entidade', 'chave']);
            $table->index(['tenant_id', 'entidade', 'ordem']);
        });

        DB::statement("ALTER TABLE crm_leads ADD COLUMN custom jsonb NOT NULL DEFAULT '{}'::jsonb");
        DB::statement("ALTER TABLE clients ADD COLUMN custom jsonb NOT NULL DEFAULT '{}'::jsonb");
        DB::statement('CREATE INDEX crm_leads_custom_gin ON crm_leads USING gin (custom)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS crm_leads_custom_gin');
        DB::statement('ALTER TABLE clients DROP COLUMN IF EXISTS custom');
        DB::statement('ALTER TABLE crm_leads DROP COLUMN IF EXISTS custom');
        Schema::dropIfExists('crm_campos_custom');
    }
};
