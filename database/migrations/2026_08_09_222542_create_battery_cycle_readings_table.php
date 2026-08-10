<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historico de leituras de ciclo de bateria -- mesmo padrao de
     * horimeter_readings (1 linha por evento de leitura, nao sobrescrita),
     * necessario pra ter uma fonte de dado incremental que alimente
     * MaintenancePlan::dueStatusForAsset() (gatilho por ciclos de bateria,
     * relevante pra PTA/empilhadeira eletrica).
     */
    public function up(): void
    {
        Schema::create('battery_cycle_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cycles');
            $table->timestamp('recorded_at');
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battery_cycle_readings');
    }
};
