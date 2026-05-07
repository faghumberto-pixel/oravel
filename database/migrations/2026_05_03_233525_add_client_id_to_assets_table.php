<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // INCLUSÃO MÍNIMA: Cria o vínculo com o cliente
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete()->after('current_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });
    }
}; // A CLASSE TERMINA AQUI. NADA DEVE VIR DEPOIS DISSO NESTE ARQUIVO.