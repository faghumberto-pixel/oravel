<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mesmo padrao de fleet_vehicle_documents -- documentos ALEM da CNH (que
 * fica em colunas proprias em fleet_drivers, pra badge de vencimento sem
 * join). Aqui entram MOPP (cargas perigosas), certificado de operador de
 * equipamento, etc -- "se aplicavel", varia por tenant/setor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_driver_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('fleet_driver_id')->constrained()->cascadeOnDelete();
            $table->string('tipo');
            $table->date('data_emissao')->nullable();
            $table->date('data_vencimento')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_driver_documents');
    }
};
