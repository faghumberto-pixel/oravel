<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedupe do comando maintenance:check-due-alerts -- 1 linha por
 * Ativo+Plano ja alertado. Ao rodar de novo: se ainda vencido e
 * alertado ha menos de 7 dias, nao repete; se nao esta mais vencido
 * (manutencao feita), a linha e apagada pra permitir um alerta novo no
 * proximo ciclo de vencimento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_due_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('maintenance_plan_id')->constrained()->cascadeOnDelete();
            $table->timestamp('alerted_at');
            $table->timestamps();

            $table->unique(['asset_id', 'maintenance_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_due_alerts');
    }
};
