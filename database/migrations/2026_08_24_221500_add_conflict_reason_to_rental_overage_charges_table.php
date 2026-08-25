<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suporta RentalOverageCharge::STATUS_CONFLICT -- pedido do usuário
 * 2026-08-24: cálculo automático de excedente não aprova sozinho quando
 * não confia no resultado (contratos sobrepostos no mesmo Asset, ou
 * leitura de horímetro insuficiente no período).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_overage_charges', function (Blueprint $table) {
            $table->text('conflict_reason')->nullable()->after('status');
            $table->decimal('hours_used', 10, 2)->nullable()->change();
            $table->decimal('hours_overage', 10, 2)->nullable()->change();
            $table->decimal('amount', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rental_overage_charges', function (Blueprint $table) {
            $table->dropColumn('conflict_reason');
            $table->decimal('hours_used', 10, 2)->nullable(false)->change();
            $table->decimal('hours_overage', 10, 2)->nullable(false)->change();
            $table->decimal('amount', 12, 2)->nullable(false)->change();
        });
    }
};
