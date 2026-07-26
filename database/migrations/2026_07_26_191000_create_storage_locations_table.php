<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Posicao estruturada de planta baixa (quadrante/prateleira), usada
     * tanto pelo almoxarifado (localizar peca) quanto pelo patio de ativos
     * (localizar equipamento) -- 1 model so, distinguido por "context",
     * pra nao forcar o componente de grade (PlantaBaixaGrid) a lidar com
     * duas fontes de dado heterogeneas.
     */
    public function up(): void
    {
        Schema::create('storage_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('internal_unit_id')->constrained('internal_units')->cascadeOnDelete();
            $table->string('context'); // 'almoxarifado' | 'patio_ativos'
            $table->string('code'); // ex: "A1-03"
            $table->string('label')->nullable();
            $table->unsignedInteger('row');
            $table->unsignedInteger('column');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['internal_unit_id', 'context', 'row', 'column'], 'storage_locations_grid_unique');
            $table->unique(['tenant_id', 'context', 'code'], 'storage_locations_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_locations');
    }
};
