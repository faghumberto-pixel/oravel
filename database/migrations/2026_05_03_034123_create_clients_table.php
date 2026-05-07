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
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // AJUSTE: Alterado para foreignUuid para compatibilidade com o sistema SaaS
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            
            // Dados de Identificação
            $table->string('name'); // Nome ou Razão Social
            $table->string('cpf_cnpj')->nullable();
            $table->string('contact_name')->nullable(); // Nome do representante
            
            // Endereço
            $table->string('cep')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->char('uf', 2)->nullable();
            
            // Contato
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            
            $table->timestamps();
            $table->softDeletes(); // Permite "lixeira" para não perder histórico
        });
    }

    /** * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};