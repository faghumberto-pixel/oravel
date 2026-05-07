<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            // Adiciona o motivo da reprogramação se não existir
            if (!Schema::hasColumn('maintenance_orders', 'reschedule_reason')) {
                $table->text('reschedule_reason')->nullable();
            }

            // Adiciona o motivo da transferência se não existir
            if (!Schema::hasColumn('maintenance_orders', 'transfer_reason')) {
                $table->text('transfer_reason')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_orders', 'reschedule_reason')) {
                $table->dropColumn('reschedule_reason');
            }

            if (Schema::hasColumn('maintenance_orders', 'transfer_reason')) {
                $table->dropColumn('transfer_reason');
            }
        });
    }
};