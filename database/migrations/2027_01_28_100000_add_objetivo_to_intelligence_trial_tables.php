<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Objetivo escolhido na etapa 1 do cadastro da landing (geral, rentabilidade, frota, custos). Só informativo, vai para o lead e para o aviso ao dono. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['intelligence_trial_pendentes', 'intelligence_trials'] as $tabela) {
            if (! Schema::hasColumn($tabela, 'objetivo')) {
                Schema::table($tabela, fn (Blueprint $t) => $t->string('objetivo', 30)->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (['intelligence_trial_pendentes', 'intelligence_trials'] as $tabela) {
            if (Schema::hasColumn($tabela, 'objetivo')) {
                Schema::table($tabela, fn (Blueprint $t) => $t->dropColumn('objetivo'));
            }
        }
    }
};
