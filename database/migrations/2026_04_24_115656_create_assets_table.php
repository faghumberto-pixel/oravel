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
        Schema::create('ativos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id'); // Obrigatório
            $table->string('nome');
            $table->string('codigo')->unique();
            // Adicione outros campos relevantes para um ativo aqui
            $table->timestamps();

            // Adicionando a chave estrangeira para garantir a integridade no nível do banco.
            // Assumindo que você terá uma tabela 'tenants'. Se não tiver ainda, pode comentar esta linha.
            // $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ativos');
    }
};