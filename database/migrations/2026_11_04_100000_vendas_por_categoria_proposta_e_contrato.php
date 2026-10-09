<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Linha da proposta de Acessório/Insumo pode apontar pro tipo do catálogo de Itens Agregados.
        Schema::table('proposta_comercial_itens', function (Blueprint $table) {
            $table->foreignUuid('aggregate_item_type_id')->nullable()->constrained('aggregate_item_types')->nullOnDelete();
        });

        // Contrato de serviço contratado separado da locação: service_category preenchido
        // (mao_de_obra, seguranca_documentacao, acessorio, insumo); null = contrato de locação.
        // Contrato de serviço não tem equipamento: asset_id era NOT NULL no banco.
        DB::statement('ALTER TABLE contracts ALTER COLUMN asset_id DROP NOT NULL');

        Schema::table('contracts', function (Blueprint $table) {
            $table->string('service_category')->nullable()->index();
            $table->foreignUuid('proposta_comercial_id')->nullable()->constrained('proposta_comerciais')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proposta_comercial_id');
            $table->dropColumn('service_category');
        });

        Schema::table('proposta_comercial_itens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aggregate_item_type_id');
        });
    }
};
