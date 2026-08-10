<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Specs tecnicas de Plataforma de Trabalho Aerea (tesoura/articulada/
     * telescopica/mastro vertical) -- mesmo padrao arquitetural de
     * asset_forklift_specifications (1:1 com Asset, editado embutido no
     * AssetResource, sem Resource proprio). O par generico capacity_value/
     * capacity_unit do Asset so' comporta UMA dimensao tecnica por vez;
     * PTA precisa de altura de trabalho + altura da plataforma + alcance +
     * capacidade da cesta + peso operacional simultaneamente.
     */
    public function up(): void
    {
        Schema::create('asset_platform_specifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('platform_type')->nullable();
            $table->string('energy_type')->nullable();
            $table->decimal('working_height_m', 6, 2)->nullable();
            $table->decimal('platform_height_m', 6, 2)->nullable();
            $table->decimal('horizontal_outreach_m', 6, 2)->nullable();
            $table->decimal('platform_capacity_kg', 10, 2)->nullable();
            $table->decimal('operational_weight_kg', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_platform_specifications');
    }
};
