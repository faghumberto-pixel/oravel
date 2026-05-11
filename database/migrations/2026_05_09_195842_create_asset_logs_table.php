<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asset_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Relacionamento com o Ativo (FK)
            $table->foreignUuid('asset_id')
                  ->constrained('assets')
                  ->onDelete('cascade');

            // Relacionamento com o Tenant para isolamento de dados
            $table->foreignUuid('tenant_id');

            $table->string('action'); // Ex: 'checklist_failure', 'status_change'
            
            // Detalhes do log (quais itens falharam, quem alterou, etc)
            $table->jsonb('details')->nullable();

            $table->timestamps();
            
            // Índice para performance em filtros por ativo ou tenant
            $table->index(['asset_id', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_logs');
    }
};
