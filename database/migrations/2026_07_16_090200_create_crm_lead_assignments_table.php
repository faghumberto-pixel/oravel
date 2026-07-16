<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historico de atribuicao de vendedor -- MaintenanceOrderDelegation
 * (unico precedente parecido no app) e' fino demais pra reconstruir
 * "quem teve esse lead e quando" (so tem delegated_to_user_id, sem
 * from/quem mudou). Registro imutavel: sem updated_at de proposito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_lead_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('crm_lead_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('from_user_id')->nullable()->constrained('users');
            $table->foreignUuid('to_user_id')->constrained('users');
            $table->foreignUuid('changed_by_user_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_assignments');
    }
};
