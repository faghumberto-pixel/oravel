<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link explicito, nao fusao de tabelas: PartsRequest continua sendo o
 * alerta automatico de estoque baixo (1 material, sem fluxo de
 * aprovacao/cotacao); MaterialRequest e' a requisicao formal completa.
 * Este campo so' marca que um PartsRequest ja foi "promovido" a uma
 * requisicao formal, pra nao promover duas vezes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts_requests', function (Blueprint $table) {
            $table->uuid('converted_to_material_request_id')->nullable()->after('status');
            $table->foreign('converted_to_material_request_id', 'parts_requests_material_request_fk')
                ->references('id')->on('material_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('parts_requests', function (Blueprint $table) {
            $table->dropForeign('parts_requests_material_request_fk');
            $table->dropColumn('converted_to_material_request_id');
        });
    }
};
