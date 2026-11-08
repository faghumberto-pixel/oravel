<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** G1: o diff de template e calculado sob demanda (fase 6); a coluna reservada na fase 1 nunca foi usada. Tabela nova, sem dado de producao. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('catalogo_instalacoes', 'diff_pendente')) {
            Schema::table('catalogo_instalacoes', function (Blueprint $table) {
                $table->dropColumn('diff_pendente');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('catalogo_instalacoes', 'diff_pendente')) {
            Schema::table('catalogo_instalacoes', function (Blueprint $table) {
                $table->jsonb('diff_pendente')->nullable();
            });
        }
    }
};
