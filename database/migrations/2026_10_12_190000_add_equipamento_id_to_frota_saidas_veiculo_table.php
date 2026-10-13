<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('frota_saidas_veiculo', function (Blueprint $table) {
            // Equipamento (ativo) que o veículo leva nesta saída, quando levar (guindaste na carreta, empilhadeira etc.).
            $table->uuid('equipamento_id')->nullable()->after('ativo_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('frota_saidas_veiculo', function (Blueprint $table) {
            $table->dropColumn('equipamento_id');
        });
    }
};
