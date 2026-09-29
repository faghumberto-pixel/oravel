<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tipo_equipamento só é obrigatório no formulário quando subject_to_nr13
 * é true (AssetResource.php, campo dentro da Section::relationship
 * 'nr13Specification') -- mas a coluna era NOT NULL sem default, então
 * salvar QUALQUER Ativo com subject_to_nr13=false (o caso comum, a
 * maioria dos equipamentos não é caldeira/vaso de pressão) quebrava com
 * "not null constraint violation" nesta coluna. Achado 29/09/2026
 * testando o formulário de criação de Ativo pela primeira vez de ponta a
 * ponta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_nr13_specifications', function (Blueprint $table) {
            $table->string('tipo_equipamento')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('asset_nr13_specifications', function (Blueprint $table) {
            $table->string('tipo_equipamento')->nullable(false)->change();
        });
    }
};
