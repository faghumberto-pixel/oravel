<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ContractResource::form() ja tinha os campos "Frete de Ida/Volta
 * incluso?" (Toggle) e "Responsavel pela Manutencao" (Select) desde a
 * criacao do formulario -- mas nunca existiram como coluna nem como
 * fillable, entao o usuario preenchia e o valor era descartado
 * silenciosamente no save() (mass assignment).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('frete_incluso')->default(false)->after('maintenance_clause');
            $table->string('responsavel_manutencao')->nullable()->after('frete_incluso');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['frete_incluso', 'responsavel_manutencao']);
        });
    }
};
