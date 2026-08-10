<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Specs tecnicas de Gerador de Energia -- mesmo padrao arquitetural de
     * asset_forklift_specifications/asset_platform_specifications (1:1 com
     * Asset, editado embutido no AssetResource, sem Resource proprio).
     * Gap apontado no diagnostico: nenhum campo de tensao/voltagem,
     * capacidade de tanque ou tipo de partida existia em lugar nenhum --
     * nem o seeder de demonstracao mais elaborado (DemoGeradoresRmcSeeder)
     * usava algo alem de capacity_value/capacity_unit generico.
     */
    public function up(): void
    {
        Schema::create('asset_generator_specifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('voltage_type')->nullable();
            $table->string('voltage')->nullable();
            $table->decimal('fuel_tank_capacity_l', 8, 2)->nullable();
            $table->string('starter_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_generator_specifications');
    }
};
