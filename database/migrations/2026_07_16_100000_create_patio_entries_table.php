<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de portaria -- QUALQUER veiculo que chega na unidade (visita,
 * fornecedor, entrega de pecas, mobilizacao/desmobilizacao), nao
 * confundir com EquipmentPatioArrival (laudo de recebimento fisico,
 * preso 1:1 a uma EquipmentMovement ja concluida -- outra coisa).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patio_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('plate')->nullable();
            $table->boolean('is_company_vehicle')->default(false);
            $table->string('driver_name')->nullable();
            $table->string('driver_document')->nullable();
            $table->string('origin')->nullable();
            $table->string('reason');
            $table->text('reason_detail')->nullable();
            $table->boolean('brings_equipment')->default(false);
            $table->boolean('equipment_is_company')->default(false);
            $table->foreignUuid('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('arrived_at');
            $table->foreignUuid('registered_by_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patio_entries');
    }
};
