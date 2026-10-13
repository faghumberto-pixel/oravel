<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('frota_saidas_veiculo', function (Blueprint $table) {
            // Cada registro é um TRECHO: sai de uma origem e chega a um destino (empresa, cliente ou outro local).
            $table->string('origem_tipo', 10)->default('empresa')->after('equipamento_id');
            $table->foreignUuid('origem_cliente_id')->nullable()->after('origem_tipo')->constrained('clients')->nullOnDelete();
            $table->string('origem')->nullable()->after('origem_cliente_id');
            $table->string('destino_tipo', 10)->default('outro')->after('origem');
            $table->foreignUuid('percurso_id')->nullable()->after('destino_tipo')->constrained('frota_percursos')->nullOnDelete();
        });

        // Registros antigos: saíam da empresa; quem já tinha cliente como destino é destino "cliente".
        DB::table('frota_saidas_veiculo')->whereNotNull('cliente_id')->update(['destino_tipo' => 'cliente']);
        DB::table('frota_saidas_veiculo')->update(['origem' => 'Empresa']);
    }

    public function down(): void
    {
        Schema::table('frota_saidas_veiculo', function (Blueprint $table) {
            $table->dropConstrainedForeignId('percurso_id');
            $table->dropConstrainedForeignId('origem_cliente_id');
            $table->dropColumn(['origem_tipo', 'origem', 'destino_tipo']);
        });
    }
};
