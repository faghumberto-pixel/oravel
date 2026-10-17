<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identidade de pacote no contrato (Locadora, CRM, Estoque, Logística). Os contratos que já existem são todos
     * do ERP completo, que é de locadora; nulo = "Personalizado" (conjunto de módulos montado à mão).
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('pacote', 20)->nullable()->after('name');
            $table->index('pacote');
        });

        DB::table('plans')->update(['pacote' => 'locadora']);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropIndex(['pacote']);
            $table->dropColumn('pacote');
        });
    }
};
