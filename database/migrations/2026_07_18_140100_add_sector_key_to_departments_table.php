<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Department e' hoje 100% texto livre por tenant (confirmado: nenhuma
 * tabela/enum canonico existe) -- sector_key resolve "qual departamento
 * deste tenant e' a Logistica de verdade", pra travas de hierarquia que
 * precisam de certeza (nao dependem do nome/codigo digitado a mao pelo
 * tenant). Nullable: departamento "customizado" sem chave fica de fora
 * das travas novas, sem quebrar nada existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (! Schema::hasColumn('departments', 'sector_key')) {
                $table->string('sector_key')->nullable()->after('code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'sector_key')) {
                $table->dropColumn('sector_key');
            }
        });
    }
};
