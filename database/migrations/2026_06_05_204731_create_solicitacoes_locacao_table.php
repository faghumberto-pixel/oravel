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
        Schema::create('solicitacoes_locacao', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID como PK
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            
            // Ajustado para foreignId para manter compatibilidade com a PK 'id' da tabela 'users'
            $table->foreignId('user_id')->constrained('users');
            
            $table->foreignUuid('customer_id')->constrained('clients');
            $table->foreignUuid('contract_id')->nullable()->constrained('contracts');
            $table->foreignUuid('category_id')->constrained('asset_categories'); 
            $table->foreignUuid('asset_id')->nullable()->constrained('assets');
            $table->string('purpose')->nullable(); 
            $table->date('data_saida_prevista');
            $table->string('status_comercial')->default('proposta_em_andamento');
            $table->text('observations')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitacoes_locacao');
    }
};