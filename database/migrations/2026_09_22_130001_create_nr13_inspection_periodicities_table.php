<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De-para configurável (tipo_equipamento + categoria_risco -> intervalo em meses), editável
 * pelo tenant -- NÃO é uma tabela legal fixa. A periodicidade real de inspeção por NR-13 varia
 * por categoria de risco (PV pra caldeiras, grupo de potencial de risco pra vasos) e pode ser
 * estendida via Plano de Inspeção baseado em risco (PCPI/RBI), sob responsabilidade de
 * profissional habilitado. O seeder só popula um ponto de partida comum (ver Nr13InspectionPeriodicitySeeder);
 * a decisão técnica de qual intervalo vale pra cada equipamento é do responsável do tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nr13_inspection_periodicities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('tipo_equipamento');
            $table->string('categoria_risco')->nullable();
            $table->unsignedInteger('intervalo_meses');
            $table->timestamps();

            $table->unique(['tenant_id', 'tipo_equipamento', 'categoria_risco']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nr13_inspection_periodicities');
    }
};
