<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pendencia = registro separado do status do Kanban (que ja tem uma
 * coluna "Pendencia" com outro significado, workflow_status da OS) --
 * aberta a partir de uma Ordem de Servico, notifica Supervisor/Gerente/
 * Analista de Manutencao (MaintenanceOrderPendenciaObserver), aparece em
 * App\Filament\Pages\EventosEFalhas ate ser marcada como resolvida.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_order_pendencias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('maintenance_order_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->foreignUuid('created_by_user_id')->constrained('users');
            $table->string('status')->default('aberta');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUuid('resolved_by_user_id')->nullable()->constrained('users');
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_order_pendencias');
    }
};
