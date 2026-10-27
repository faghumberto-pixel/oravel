<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dashboards personalizados por empresa. Um modelo (dashboard_templates) é ou da plataforma (tenant_id nulo,
     * vale para várias empresas) ou exclusivo de uma empresa (tenant_id preenchido, copiado de um modelo e ajustado).
     * Os blocos do modelo vêm do catálogo em código (App\Dashboards\BlockRegistry) e guardam configuração própria
     * e a ficha opcional "dor do cliente -> indicador -> meta -> revisão". A atribuição liga modelo e empresa.
     */
    public function up(): void
    {
        Schema::create('dashboard_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->uuid('source_template_id')->nullable();
            $table->string('name', 120);
            $table->string('profile', 30)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });

        // Chave que aponta para a própria tabela: só pode ser criada depois da tabela existir.
        Schema::table('dashboard_templates', function (Blueprint $table) {
            $table->foreign('source_template_id')->references('id')->on('dashboard_templates')->nullOnDelete();
        });

        Schema::create('dashboard_template_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dashboard_template_id')->constrained('dashboard_templates')->cascadeOnDelete();
            $table->string('block_key', 80);
            $table->unsignedSmallInteger('position')->default(0);
            $table->json('config')->nullable();
            $table->text('ficha_dor')->nullable();
            $table->string('ficha_indicador', 160)->nullable();
            $table->string('ficha_meta', 160)->nullable();
            $table->date('ficha_revisao_em')->nullable();
            $table->timestamps();

            $table->index(['dashboard_template_id', 'position']);
        });

        Schema::create('dashboard_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('dashboard_template_id')->constrained('dashboard_templates')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'dashboard_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_assignments');
        Schema::dropIfExists('dashboard_template_blocks');
        Schema::dropIfExists('dashboard_templates');
    }
};
