<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gap apontado no diagnostico de Plataformas de Trabalho Aerea: nenhuma
     * tabela tinha campo de tipo de veiculo exigido (prancha, munck) ou de
     * seguro/apolice de transporte -- necessario antes de operar logistica
     * de PTA telescopica/articulada de grande porte.
     */
    public function up(): void
    {
        Schema::table('freight_carriers', function (Blueprint $table) {
            $table->jsonb('vehicle_types')->nullable();
            $table->string('insurance_policy_number')->nullable();
            $table->decimal('insurance_coverage_value', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('freight_carriers', function (Blueprint $table) {
            $table->dropColumn(['vehicle_types', 'insurance_policy_number', 'insurance_coverage_value']);
        });
    }
};
