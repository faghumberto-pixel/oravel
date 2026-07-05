<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_maintenance_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('fleet_vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('fleet_maintenance_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tipo_servico');
            $table->decimal('km_na_execucao', 12, 2)->nullable();
            $table->date('data_execucao');
            $table->decimal('custo', 10, 2)->nullable();
            $table->string('fornecedor')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_maintenance_history');
    }
};
