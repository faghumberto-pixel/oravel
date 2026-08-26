<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alocacao de tecnico com periodo (starts_at/ends_at) para desenhar o
 * Gantt em Alocacao de Tecnicos (PMP) -- Appointment e MaintenanceOrder so
 * tem um timestamp pontual (scheduled_at), sem duracao, insuficiente pra
 * uma visao de raia por tecnico. maintenance_order_id fica nullable
 * porque a alocacao pode nascer direto de um MaintenanceDueAlert (item
 * "A Fazer" que ainda nao virou OS de verdade).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('technician_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('maintenance_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('maintenance_due_alert_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('planejado');
            $table->timestamps();

            $table->index(['technician_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_allocations');
    }
};
