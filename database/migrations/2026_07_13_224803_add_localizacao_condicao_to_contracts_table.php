<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Local de instalacao do equipamento locado -- ate aqui cep_obra/
 * latitude_obra/longitude_obra existiam na tabela mas nenhum formulario
 * usava (colunas mortas). local_tipo decide de onde Contract::resolvedLocation()
 * le o endereco: sede_empresa -> internal_units, endereco_cliente ->
 * clients (ja cadastrado, sem duplicar), outro -> os campos proprios
 * abaixo (obrigatorios so nesse caso, ver ContractResource).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('local_tipo')->default('sede_empresa')->after('asset_id');
            $table->foreignUuid('internal_unit_id')->nullable()->after('local_tipo')
                ->constrained('internal_units')->nullOnDelete();
            $table->string('endereco_obra')->nullable()->after('cep_obra');
            $table->string('cidade_obra')->nullable()->after('endereco_obra');
            $table->string('uf_obra', 2)->nullable()->after('cidade_obra');
            $table->string('condicao_ambiente')->nullable()->after('uf_obra');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('internal_unit_id');
            $table->dropColumn(['local_tipo', 'endereco_obra', 'cidade_obra', 'uf_obra', 'condicao_ambiente']);
        });
    }
};
