<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkpoints manuais de localizacao durante o transporte (saida do patio,
 * checkpoint intermediario, chegada no destino/cliente, saida do cliente,
 * chegada no patio). Colunas reais (nao Media custom_properties, que e' o
 * outro padrao usado nas fotos do checklist) -- precisa ser consultavel/
 * ordenavel pra desenhar a rota no mapa (mesmo Leaflet ja usado no Mapa
 * de Leads do CRM).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_movement_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('equipment_movement_id')->constrained()->cascadeOnDelete();
            $table->string('checkpoint_type');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('address')->nullable();
            $table->timestamp('captured_at');
            $table->foreignUuid('captured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_movement_locations');
    }
};
