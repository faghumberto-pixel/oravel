<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Antes so' dava pra inferir "veio de OS ou nao" por maintenance_order_id
     * ser nulo -- nao distinguia reposicao proativa de estoque (nova Page
     * RequisicaoReposicaoEstoque) de requisicao manual avulsa nem de
     * conversao de PartsRequest (PartsRequestResource::converter_em_requisicao).
     */
    public function up(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->string('origin')->default('manual')->after('maintenance_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
