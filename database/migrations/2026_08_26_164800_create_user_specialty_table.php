<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: especialidades de manutencao que um tecnico (User) atende --
 * eletrico, hidraulico, etc. Reaproveita o vocabulario ja existente de
 * MaintenanceOrder::FAILURE_CATEGORY_* (coluna 'specialty' grava um desses
 * valores) em vez de criar uma tabela de catalogo nova. Mesmo padrao de
 * fleet_driver_vehicle (id incremental, sem tenant_id proprio -- o
 * isolamento vem de User ja ser tenant-scoped).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_specialty', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('specialty');
            $table->timestamps();
            $table->unique(['user_id', 'specialty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_specialty');
    }
};
