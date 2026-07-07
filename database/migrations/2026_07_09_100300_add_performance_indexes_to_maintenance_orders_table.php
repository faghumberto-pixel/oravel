<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            // Suporta os relatórios de reincidência de OS por ativo e a
            // Agenda/Desempenho do técnico (AgendaTecnico já filtra por
            // technician_id + scheduled_at; os relatórios de desempenho e
            // retrabalho vão filtrar por technician_id + status).
            $table->index(['asset_id', 'created_at'], 'maintenance_orders_asset_created_idx');
            $table->index(['technician_id', 'status'], 'maintenance_orders_technician_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropIndex('maintenance_orders_asset_created_idx');
            $table->dropIndex('maintenance_orders_technician_status_idx');
        });
    }
};
