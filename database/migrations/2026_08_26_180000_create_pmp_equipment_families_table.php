<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogo global de familias de equipamento pra templates de PMP --
 * SEM tenant_id de proposito, mesmo padrao de 'plans' (Plan.php nao usa
 * BelongsToTenant): dado mantido pelo super admin no painel central,
 * reutilizavel entre qualquer tenant do mesmo segmento de equipamento.
 * Import pro tenant e' feito copiando pra MaintenancePlan (tenant-scoped),
 * nunca lido direto por telas do painel admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pmp_equipment_families', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('segment');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pmp_equipment_families');
    }
};
