<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Histórico das importações reais de planilha do CRM (quem importou o quê, com os totais e o relatório). */
    public function up(): void
    {
        Schema::create('crm_importacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('destino', 20);
            $table->string('arquivo');
            $table->unsignedInteger('criados')->default(0);
            $table->unsignedInteger('atualizados')->default(0);
            $table->unsignedInteger('ignorados')->default(0);
            $table->unsignedInteger('erros')->default(0);
            $table->unsignedInteger('avisos')->default(0);
            $table->jsonb('relatorio')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_importacoes');
    }
};
