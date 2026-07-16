<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CriticalityLevel ja existia (code/name/color) mas sem tela de cadastro
 * -- unificando com AbcMatrix (A/B/C hardcoded em 4 lugares diferentes)
 * e o Kanban (que ja usa este model pra decidir vermelho, so que via
 * comparacao fixa code === 'alta'). is_urgent vira o criterio
 * configuravel que substitui essa comparacao fixa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('criticality_levels', function (Blueprint $table) {
            $table->boolean('is_urgent')->default(false);
            $table->integer('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('criticality_levels', function (Blueprint $table) {
            $table->dropColumn(['is_urgent', 'sort_order']);
        });
    }
};
